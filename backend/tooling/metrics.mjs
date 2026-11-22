// Menghitung metrik mentah (LOC, SLOC, cyclomatic complexity, hitungan Halstead) untuk file JS/TS.
// Hanya MEM-PARSE file (ts.createSourceFile); kode target tidak dieksekusi, tsconfig/plugin target tidak dimuat.
// Dipanggil dari PHP dengan cwd = root workspace:
//   node metrics.mjs [--max-bytes N] -- path/relatif/a.js path/relatif/b.tsx
// Keluaran stdout: {"files": [...], "errors": [{"path", "reason"}]}. Rumus (volume, MI, dll.) dihitung di PHP.
import fs from 'node:fs';
import path from 'node:path';
import ts from 'typescript';

const K = ts.SyntaxKind;

const SCRIPT_KINDS = {
  '.js': ts.ScriptKind.JSX, // file .js sering memuat JSX
  '.jsx': ts.ScriptKind.JSX,
  '.mjs': ts.ScriptKind.JS,
  '.cjs': ts.ScriptKind.JS,
  '.ts': ts.ScriptKind.TS,
  '.mts': ts.ScriptKind.TS,
  '.cts': ts.ScriptKind.TS,
  '.tsx': ts.ScriptKind.TSX,
};

const FUNCTION_KINDS = new Set([
  K.FunctionDeclaration, K.FunctionExpression, K.ArrowFunction, K.MethodDeclaration,
  K.Constructor, K.GetAccessor, K.SetAccessor,
]);

const DECISION_KINDS = new Set([
  K.IfStatement, K.ForStatement, K.ForInStatement, K.ForOfStatement, K.WhileStatement,
  K.DoStatement, K.CaseClause, K.CatchClause, K.ConditionalExpression,
]);

const LOGICAL_OPERATORS = new Set([
  K.AmpersandAmpersandToken, K.BarBarToken, K.QuestionQuestionToken,
  K.AmpersandAmpersandEqualsToken, K.BarBarEqualsToken, K.QuestionQuestionEqualsToken,
]);

const OPERAND_KINDS = new Set([
  K.Identifier, K.PrivateIdentifier, K.StringLiteral, K.NumericLiteral, K.BigIntLiteral,
  K.NoSubstitutionTemplateLiteral, K.TemplateHead, K.TemplateMiddle, K.TemplateTail,
  K.RegularExpressionLiteral, K.JsxText, K.TrueKeyword, K.FalseKeyword, K.NullKeyword,
]);

// Pasangan kurung dihitung sekali dalam Halstead (hanya yang membuka); penutup tetap dihitung sebagai baris kode.
const CLOSING_KINDS = new Set([K.CloseParenToken, K.CloseBracketToken, K.CloseBraceToken]);
const IGNORED_KINDS = new Set([K.EndOfFileToken, K.SyntaxList]);

/** CC = 1 + titik keputusan. Dengan `stopAtFunctions`, fungsi bersarang dihitung terpisah. */
function decisions(root, stopAtFunctions) {
  let count = 0;
  const visit = (node) => {
    if (stopAtFunctions && node !== root && FUNCTION_KINDS.has(node.kind)) return;
    if (DECISION_KINDS.has(node.kind)) count++;
    else if (node.kind === K.BinaryExpression && LOGICAL_OPERATORS.has(node.operatorToken.kind)) count++;
    ts.forEachChild(node, visit);
  };
  visit(root);
  return count;
}

/** Token daun (tanpa komentar/trivia dan tanpa node JSDoc), berurutan menurut posisi. */
function leafTokens(sf) {
  const tokens = [];
  const stack = [sf];
  while (stack.length > 0) {
    const node = stack.pop();
    if (node.kind >= K.FirstJSDocNode && node.kind <= K.LastJSDocNode) continue;
    const children = node.getChildren(sf);
    if (children.length === 0) {
      if (IGNORED_KINDS.has(node.kind)) continue;
      if (node.kind === K.JsxText && node.containsOnlyTriviaWhiteSpaces) continue;
      const operand = OPERAND_KINDS.has(node.kind);
      tokens.push({
        start: node.getStart(sf),
        end: node.end,
        closing: CLOSING_KINDS.has(node.kind),
        operand,
        key: operand ? `o:${node.getText(sf)}` : `p:${node.kind}`,
      });
      continue;
    }
    for (let i = children.length - 1; i >= 0; i--) stack.push(children[i]);
  }
  return tokens;
}

function halstead(tokens, from, to) {
  const operators = new Map();
  const operands = new Map();
  // Pencarian biner token pertama dengan start >= from.
  let low = 0;
  let high = tokens.length;
  while (low < high) {
    const mid = (low + high) >> 1;
    if (tokens[mid].start < from) low = mid + 1;
    else high = mid;
  }
  let total1 = 0;
  let total2 = 0;
  for (let i = low; i < tokens.length && tokens[i].end <= to; i++) {
    const token = tokens[i];
    if (token.closing) continue;
    const bucket = token.operand ? operands : operators;
    bucket.set(token.key, 1);
    if (token.operand) total2++;
    else total1++;
  }
  return { n1: operators.size, n2: operands.size, N1: total1, N2: total2 };
}

function functionName(node, sf) {
  const clean = (text) => text.replace(/\s+/g, ' ').slice(0, 200);
  let name = null;
  if (node.kind === K.Constructor) name = 'constructor';
  else if (node.name) name = node.name.getText(sf);
  else if (node.parent) {
    const p = node.parent;
    if ((p.kind === K.VariableDeclaration || p.kind === K.PropertyAssignment || p.kind === K.PropertyDeclaration) && p.name) {
      name = p.name.getText(sf);
    } else if (p.kind === K.BinaryExpression && p.operatorToken.kind === K.EqualsToken) {
      name = p.left.getText(sf);
    }
  }
  name = clean(name ?? '<anonymous>');
  const owner = node.parent;
  if (owner && (owner.kind === K.ClassDeclaration || owner.kind === K.ClassExpression) && owner.name) {
    return clean(`${owner.name.getText(sf)}.${name}`);
  }
  return name;
}

function analyze(relativePath, text) {
  const extension = path.extname(relativePath).toLowerCase();
  const sf = ts.createSourceFile(relativePath, text, ts.ScriptTarget.Latest, true, SCRIPT_KINDS[extension] ?? ts.ScriptKind.TS);

  if ((sf.parseDiagnostics ?? []).length > 0) return { error: 'parse_error' };

  const tokens = leafTokens(sf);
  const lineStarts = sf.getLineStarts();
  const lineOf = (pos) => sf.getLineAndCharacterOfPosition(pos).line;

  const codeLines = new Uint8Array(lineStarts.length);
  for (const token of tokens) {
    const last = lineOf(Math.max(token.start, token.end - 1));
    for (let line = lineOf(token.start); line <= last; line++) codeLines[line] = 1;
  }
  const slocBetween = (fromLine, toLine) => {
    let count = 0;
    for (let line = fromLine; line <= toLine; line++) count += codeLines[line];
    return count;
  };

  const functions = [];
  const visit = (node) => {
    if (FUNCTION_KINDS.has(node.kind) && node.body) {
      const start = node.getStart(sf);
      const startLine = lineOf(start);
      const endLine = lineOf(Math.max(start, node.end - 1));
      functions.push({
        name: functionName(node, sf),
        line: startLine + 1,
        loc: endLine - startLine + 1,
        sloc: slocBetween(startLine, endLine),
        cc: 1 + decisions(node, true),
        ...halstead(tokens, start, node.end),
      });
    }
    ts.forEachChild(node, visit);
  };
  visit(sf);

  const loc = text.length === 0 ? 0 : lineStarts.length - (text.endsWith('\n') ? 1 : 0);

  return {
    file: {
      path: relativePath,
      loc,
      sloc: slocBetween(0, lineStarts.length - 1),
      cc: 1 + decisions(sf, false),
      ...halstead(tokens, 0, text.length),
      functions,
    },
  };
}

function main() {
  const args = process.argv.slice(2);
  const separator = args.indexOf('--');
  const options = separator === -1 ? args : args.slice(0, separator);
  const files = separator === -1 ? [] : args.slice(separator + 1);
  const maxBytesIndex = options.indexOf('--max-bytes');
  const maxBytes = maxBytesIndex === -1 ? 1024 * 1024 : Number.parseInt(options[maxBytesIndex + 1], 10);

  const root = fs.realpathSync(process.cwd());
  const result = { files: [], errors: [] };

  for (const relativePath of files) {
    try {
      const full = path.resolve(root, relativePath);
      const stat = fs.lstatSync(full);
      const inside = full.startsWith(root + path.sep);
      if (path.isAbsolute(relativePath) || !inside || stat.isSymbolicLink() || !stat.isFile() || fs.realpathSync(full) !== full) {
        result.errors.push({ path: relativePath, reason: 'unsafe_path' });
        continue;
      }
      if (stat.size > maxBytes) {
        result.errors.push({ path: relativePath, reason: 'too_large' });
        continue;
      }
      const text = fs.readFileSync(full, 'utf8').replace(/^﻿/, '');
      const analysis = analyze(relativePath.split(path.sep).join('/'), text);
      if (analysis.error) result.errors.push({ path: relativePath, reason: analysis.error });
      else result.files.push(analysis.file);
    } catch {
      result.errors.push({ path: relativePath, reason: 'analysis_failed' });
    }
  }

  process.stdout.write(JSON.stringify(result));
}

main();
