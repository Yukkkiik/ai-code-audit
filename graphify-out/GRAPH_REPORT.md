# Graph Report - ai-code-security-audit-platform  (2026-11-23)

## Corpus Check
- 298 files · ~100,201 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 10 file(s) not represented in the graph (top: (none) 7, .xml 1, .ico 1)

## Summary
- 2601 nodes · 6494 edges · 136 communities (74 shown, 62 thin omitted)
- Extraction: 97% EXTRACTED · 3% INFERRED · 0% AMBIGUOUS · INFERRED: 212 edges (avg confidence: 0.84)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `63a0f6cc`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- Audit Dependencies Tests
- API Feature Tests
- RepositoryFileWalker
- Users Table & DB Tests
- GitUrlValidator
- Fix Match Reasons
- Composer Dependencies
- AI Tables Tests
- Package Lock Parser Tests
- OSV Client Tests
- Database Migrations
- AuditScan
- AiRefactoring Model
- ScaStageRunner Tests
- AuditScan Model
- ProcessAuditJobTest.php
- OSV Client Test Closures
- Code Language & Metric Scope
- Dependency Relation Enums
- Fix Status & Version Change
- Audit Status Enum
- Semver Version
- Audit Stage Enum
- FindingTool
- Package Json Parser Tests
- ProcessAuditJob & Store Tests
- metrics.mjs
- Audit Status Manager
- OSV Details Tests
- Manifest Type & Lock Parser
- OsvFailureReason
- AuditController.php
- Web Routes & API Errors
- Dependency Collector
- Frontend Build Packages
- ScaStageRunner Closures
- OSV Parser Tests
- OsvVulnerabilityDetail
- SastListCollection
- Illuminate\Http\Request
- Fixed Version Matcher
- Vulnerability Store Tests
- Git Cloner Tests
- Manifest JSON Reader
- HealthCategory
- Safe Zip Extractor Tests
- SourceType
- Vulnerability Store
- Store Audit Tests
- SAST & AI Schema Concepts
- Stage Status Enum
- Ingestion Exception
- Dependency & Health Controllers
- List Request Validation
- OSV Client Retry
- OSV Client Core
- Dependency Store Tests
- Architecture & Auth Concepts
- AuditVulnerabilityCombinedFixTest
- Zip Extractor Closures
- Safe Zip Extractor
- OSV Vulnerability Parser
- StoreAuditRequest.php
- SastStageRunnerTest.php
- Show Audit Tests
- PHPUnit\Framework\Attributes\DataProvider
- API Contract & Roadmap
- .parse
- CLAUDE.md Working Rules
- OSV Query & Live Test
- Result Model Tests
- AI Guardrail & State Machine
- SCA Audit Concepts
- SCA Endpoints & Roadmap Items
- API Exception Renderer
- EslintParser
- App Config Tests
- Lockfile v2 Fixture
- Queue Probe Jobs
- Repo Sandbox Safety
- SemgrepParser
- AuditScan Model Tests
- Testing Strategy
- UnresolvedReason
- CombinedFixMatchTest
- Fixture body-parser
- Fixture fsevents
- Fixture jest
- Fixture jest-util
- Fixture ms
- Decisions & Requirements
- StageResult
- Fixture express
- Fixture qs
- Fixture scoped pkg
- Fixture nested qs
- Systematic Debugging
- AI Remediation Reporting
- ToolRunStatus
- SastStageRunnerTest
- AuditWorkspace
- AuditVulnerabilityController.php
- FindingNormalizerTest
- MetricsRunnerTest
- rules
- SemgrepRunnerTest
- MetricsParser
- SastToolException
- AppServiceProvider.php
- OsvQueryResult
- .row
- SastResultStoreTest
- ManifestFailure
- EslintRunnerTest
- PHPUnit\Framework\TestCase
- ToolReport
- SastStageRunner
- GitUrlValidatorTest
- VulnerabilityDetailResource
- SastToolExecutor
- FakeHostResolver
- AuditWorkspaceTest
- DatabaseSeeder.php
- RepositoryIngestor.php
- RedisConnectionTest

## God Nodes (most connected - your core abstractions)
1. `AuditScan` - 156 edges
2. `TestCase` - 92 edges
3. `StageStatus` - 59 edges
4. `OsvClientTest` - 57 edges
5. `PackageLockParserTest` - 52 edges
6. `FixedVersionMatcherTest` - 47 edges
7. `FixMatchReason` - 44 edges
8. `DependencyCollectorTest` - 43 edges
9. `AuditStatus` - 42 edges
10. `FindingTool` - 42 edges

## Surprising Connections (you probably didn't know these)
- `sast-ast-rules skill` --references--> `TAHAP 6 SAST Engine (not started)`  [INFERRED]
  .claude/skills/sast-ast-rules/SKILL.md → IMPLEMENTATION_ROADMAP.md
- `Backend Laravel README` --conceptually_related_to--> `Project README`  [AMBIGUOUS]
  backend/README.md → README.md
- `sca-dependency-audit skill` --references--> `TAHAP 7 SCA Engine`  [EXTRACTED]
  .claude/skills/sca-dependency-audit/SKILL.md → IMPLEMENTATION_ROADMAP.md
- `requesting-code-review skill` --implements--> `Plan-First workflow (FASE A/B)`  [INFERRED]
  .claude/skills/requesting-code-review/SKILL.md → CLAUDE.md
- `test-driven-development skill` --implements--> `Plan-First workflow (FASE A/B)`  [INFERRED]
  .claude/skills/test-driven-development/SKILL.md → CLAUDE.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **AI guardrail pattern** — claude_skills_ai_remediation_guardrail_skill_prompt_injection_defense, claude_skills_ai_remediation_guardrail_skill_structured_output_validation, claude_skills_ai_remediation_guardrail_skill_static_template_fallback, claude_skills_ai_remediation_guardrail_skill_advisory_notice [EXTRACTED 0.85]
- **Repository ingestion safety controls** — claude_skills_repo_sandbox_safety_skill_zip_slip_prevention, claude_skills_repo_sandbox_safety_skill_ssrf_protection, claude_skills_repo_sandbox_safety_skill_safe_git_clone, claude_skills_repo_sandbox_safety_skill_workspace_cleanup [EXTRACTED 0.85]
- **SCA pipeline (collect, store, OSV query, store vulnerabilities, fix match)** — decisions_a30_dependency_collector, decisions_a31_osv_client, decisions_a32_sca_stage_runner, decisions_a34_fixed_version_matcher [EXTRACTED 0.85]

## Communities (136 total, 62 thin omitted)

### Community 0 - "Audit Dependencies Tests"
Cohesion: 0.07
Nodes (15): FindingTool, Eslint, Semgrep, AuditDependenciesTest, {closure#1}(), AuditFindingsTest, {closure#1}(), {closure#3}() (+7 more)

### Community 1 - "API Feature Tests"
Cohesion: 0.06
Nodes (9): HealthCheckTest, ListAuditsTest, AuditScansTableTest, DatabaseSchemaTest, DatabaseConnectionTest, ExampleTest, QueueConnectionTest, InteractsWithSchema (+1 more)

### Community 2 - "RepositoryFileWalker"
Cohesion: 0.07
Nodes (9): {closure#1}(), RepositoryFileWalker, JsTsFileSelector, JsTsSelection, {closure#1}(), SastIgnoredDirectoriesTest, RepositoryFileWalkerTest, JsTsFileSelectorTest (+1 more)

### Community 3 - "Users Table & DB Tests"
Cohesion: 0.12
Nodes (3): ManifestProject, UsersTableTest, DependencyCollectorTest

### Community 5 - "Fix Match Reasons"
Cohesion: 0.13
Nodes (7): FixMatchReason, DetailUnavailable, InvalidVersion, NoRangeData, NotInListedRanges, UnevaluableRange, FixedVersionMatcherTest

### Community 6 - "Composer Dependencies"
Cohesion: 0.04
Nodes (47): pestphp/pest-plugin, php-http/discovery, autoload, autoload-dev, psr-4, psr-4, config, allow-plugins (+39 more)

### Community 7 - "AI Tables Tests"
Cohesion: 0.10
Nodes (3): AiTablesTest, SastTablesTest, ScaTablesTest

### Community 9 - "OSV Client Tests"
Cohesion: 0.13
Nodes (3): {closure#15}(), {closure#20}(), OsvClientTest

### Community 10 - "Database Migrations"
Cohesion: 0.07
Nodes (10): {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}() (+2 more)

### Community 11 - "AuditScan"
Cohesion: 0.07
Nodes (14): MetricScope, File, Function, AuditScan, AuditScanPolicy, {closure#1}(), {closure#2}(), SastScanSummary (+6 more)

### Community 12 - "AiRefactoring Model"
Cohesion: 0.07
Nodes (17): AiSource, Llm, StaticTemplate, AiStatus, Completed, Failed, Pending, RejectedValidation (+9 more)

### Community 14 - "AuditScan Model"
Cohesion: 0.10
Nodes (4): {closure#1}(), {closure#2}(), User, AuditScanFactory

### Community 16 - "OSV Client Test Closures"
Cohesion: 0.08
Nodes (13): {closure#11}(), {closure#17}(), {closure#18}(), {closure#2}(), {closure#22}(), {closure#23}(), {closure#24}(), {closure#3}() (+5 more)

### Community 17 - "Code Language & Metric Scope"
Cohesion: 0.10
Nodes (8): AiRefactoringFactory, AiUsageLogFactory, CodeMetricFactory, DependencyFactory, ScanFindingFactory, ScanToolRunFactory, UserFactory, VulnerabilityFactory

### Community 18 - "Dependency Relation Enums"
Cohesion: 0.08
Nodes (14): DependencyRelation, Direct, Transitive, DependencyScope, Development, Optional, Peer, Production (+6 more)

### Community 19 - "Fix Status & Version Change"
Cohesion: 0.09
Nodes (12): FixMatchStatus, FixAvailable, NoFixRecorded, Undetermined, VersionChange, Major, Minor, Patch (+4 more)

### Community 20 - "Audit Status Enum"
Cohesion: 0.15
Nodes (15): AuditStatus, Completed, Failed, Ingesting, Partial, Queued, Scanning, InvalidAuditTransitionException (+7 more)

### Community 21 - "Semver Version"
Cohesion: 0.09
Nodes (4): {closure#1}(), {closure#4}(), {closure#4}(), SemverVersionTest

### Community 22 - "Audit Stage Enum"
Cohesion: 0.11
Nodes (10): AuditStage, Sast, Sca, {closure#5}(), AuditStageRunners, StageRunner, UnavailableStageRunner, IngestedRepository (+2 more)

### Community 23 - "FindingTool"
Cohesion: 0.10
Nodes (16): FindingCategory, BestPractice, Quality, Security, Severity, Critical, High, Info (+8 more)

### Community 25 - "ProcessAuditJob & Store Tests"
Cohesion: 0.17
Nodes (3): ProcessAuditJob, {closure#1}(), ProcessAuditJobTest

### Community 26 - "metrics.mjs"
Cohesion: 0.08
Nodes (27): analyze(), CLOSING_KINDS, DECISION_KINDS, decisions(), FUNCTION_KINDS, functionName(), halstead(), IGNORED_KINDS (+19 more)

### Community 30 - "Manifest Type & Lock Parser"
Cohesion: 0.11
Nodes (6): ManifestType, PackageJson, PackageLockV2, PackageLockV3, PackageLockParser, ParsedLockfile

### Community 31 - "OsvFailureReason"
Cohesion: 0.11
Nodes (9): OsvFailureReason, ClientError, InvalidResponse, PaginationIncomplete, RateLimited, ServerError, TimeBudgetExhausted, Timeout (+1 more)

### Community 32 - "AuditController.php"
Cohesion: 0.13
Nodes (5): AuditController, {closure#1}(), AuditScanCreator, CreatedAuditScan, AuditScanCreatorTest

### Community 34 - "Dependency Collector"
Cohesion: 0.22
Nodes (3): CollectedDependencies, DependencyCollector, ManifestFinder

### Community 35 - "Frontend Build Packages"
Cohesion: 0.13
Nodes (20): devDependencies, concurrently@^10.0.3, laravel-vite-plugin@^3.1, tailwindcss@^4.0.0, @tailwindcss/vite@^4.0.0, vite@^8.0.0, optionalDependencies, @laravel/multiplex@^0.4.1 (+12 more)

### Community 36 - "ScaStageRunner Closures"
Cohesion: 0.10
Nodes (20): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+12 more)

### Community 37 - "OSV Parser Tests"
Cohesion: 0.12
Nodes (7): VulnerabilitySeverity, Critical, High, Low, Medium, Unknown, OsvVulnerabilityParserTest

### Community 39 - "SastListCollection"
Cohesion: 0.12
Nodes (7): AuditCollection, DependencyCollection, FindingCollection, MetricCollection, SastListCollection, ScaListCollection, VulnerabilityCollection

### Community 40 - "Illuminate\Http\Request"
Cohesion: 0.12
Nodes (8): AuditResource, DependencyResource, FindingResource, MetricResource, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}()

### Community 41 - "Fixed Version Matcher"
Cohesion: 0.12
Nodes (6): CombinedFixResolver, {closure#2}(), {closure#3}(), FixedVersionMatcher, SemverVersion, {closure#4}()

### Community 43 - "Git Cloner Tests"
Cohesion: 0.14
Nodes (5): {closure#2}(), {closure#3}(), GitRepositoryClonerTest, RepositoryIngestorTest, FakeGitBinary

### Community 44 - "Manifest JSON Reader"
Cohesion: 0.18
Nodes (3): ManifestParseException, ManifestJsonReader, ManifestJsonReaderTest

### Community 45 - "HealthCategory"
Cohesion: 0.15
Nodes (7): HealthCategory, Fair, Good, Poor, HealthScore, HealthScoreCalculator, HealthScoreCalculatorTest

### Community 47 - "SourceType"
Cohesion: 0.11
Nodes (8): OsvQueryStatus, Failed, NotQueried, Queried, SourceType, Git, Zip, {closure#2}()

### Community 48 - "Vulnerability Store"
Cohesion: 0.16
Nodes (6): OsvVulnerabilityRef, VulnerabilityMatch, {closure#1}(), {closure#3}(), VulnerabilityStore, {closure#1}()

### Community 50 - "SAST & AI Schema Concepts"
Cohesion: 0.21
Nodes (17): Metrics: Cyclomatic Complexity, Halstead, MI, sast-ast-rules skill, Finding normalization, Health score (no SCA), Severity mapping per tool, ai_refactorings table, ai_usage_logs table (atomic daily quota), audit_scans table (+9 more)

### Community 51 - "Stage Status Enum"
Cohesion: 0.17
Nodes (12): StageStatus, Completed, Failed, Partial, Pending, Running, Skipped, ToolRunStatus (+4 more)

### Community 53 - "Dependency & Health Controllers"
Cohesion: 0.11
Nodes (6): AuditDependencyController, AuditFindingController, AuditMetricController, HealthController, Controller, AuditAccessGuard

### Community 54 - "List Request Validation"
Cohesion: 0.16
Nodes (5): ListAuditChildrenRequest, ListDependenciesRequest, ListFindingsRequest, ListMetricsRequest, ListVulnerabilitiesRequest

### Community 55 - "OSV Client Retry"
Cohesion: 0.21
Nodes (5): {closure#2}(), {closure#3}(), {closure#5}(), {closure#6}(), {closure#7}()

### Community 56 - "OSV Client Core"
Cohesion: 0.15
Nodes (3): DependencyStore, OsvClient, ScaStageRunner

### Community 58 - "Architecture & Auth Concepts"
Cohesion: 0.14
Nodes (14): Authentication and audit access rules, Architecture, Guest Token (X-Guest-Token, hashed), Backend Module Breakdown (Services/*), ProcessAuditJob orchestrator, StageRunner interface, Tech Stack (Next.js, Laravel, MySQL, Redis, Semgrep, ESLint, OSV.dev, LLM), Backend Laravel README (+6 more)

### Community 60 - "Zip Extractor Closures"
Cohesion: 0.23
Nodes (8): {closure#10}(), {closure#2}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}(), {closure#9}()

### Community 63 - "StoreAuditRequest.php"
Cohesion: 0.11
Nodes (6): NoStoreResponse, ListAuditsRequest, {closure#1}(), {closure#2}(), StoreAuditRequest, ValidatedGitUrl

### Community 64 - "SastStageRunnerTest.php"
Cohesion: 0.11
Nodes (4): {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}()

### Community 66 - "PHPUnit\Framework\Attributes\DataProvider"
Cohesion: 0.21
Nodes (5): CodeLanguage, Javascript, Typescript, FileMetrics, MetricsAggregate

### Community 67 - "API Contract & Roadmap"
Cohesion: 0.20
Nodes (11): Audit endpoints (POST/GET /audits), API Contract, Rate limit policy, AI Remediation endpoints, Report endpoints (JSON/PDF), Uniform error response format, Implementation Roadmap, TAHAP 6 SAST Engine (not started) (+3 more)

### Community 69 - "CLAUDE.md Working Rules"
Cohesion: 0.20
Nodes (11): Anti-AI-Slop rules, CLAUDE.md Working Rules, Git rules, Plan-First workflow (FASE A/B), brainstorming skill, Requirements exploration, test-driven-development skill, Red-Green-Refactor cycle (+3 more)

### Community 72 - "AI Guardrail & State Machine"
Cohesion: 0.27
Nodes (10): Advisory notice, ai-remediation-guardrail skill, Prompt injection defense (untrusted data delimiters), Static template fallback, Structured AI output validation, AI edge cases, Audit State Machine (queued, ingesting, scanning, completed/partial/failed), Flows and Error Handling (+2 more)

### Community 73 - "SCA Audit Concepts"
Cohesion: 0.24
Nodes (10): sca-dependency-audit skill, Empty result is not 'safe', Lockfile v2/v3 parsing, OSV.dev querybatch integration, Exact vs unresolved version status, SCA edge cases, Product Requirements, Scope: JS/TS SAST and npm SCA only (+2 more)

### Community 74 - "SCA Endpoints & Roadmap Items"
Cohesion: 0.36
Nodes (9): fix_match object, SCA results endpoints (dependencies, vulnerabilities), A-29 PackageLockParser, A-30 DependencyCollector, A-31 OsvClient querybatch, A-32 ScaStageRunner, A-33 SCA read endpoints, A-34 FixedVersionMatcher (+1 more)

### Community 76 - "EslintParser"
Cohesion: 0.14
Nodes (4): EslintParser, {closure#2}(), SastText, EslintParserTest

### Community 78 - "Lockfile v2 Fixture"
Cohesion: 0.22
Nodes (8): dependencies, legacy-only, resolved, version, lockfileVersion, name, requires, version

### Community 80 - "Repo Sandbox Safety"
Cohesion: 0.38
Nodes (7): Architecture Security Principles, Non-negotiable security rules, repo-sandbox-safety skill, Safe git clone via Symfony Process, Git URL SSRF protection, ZIP Slip prevention (manual entry extraction), Ingestion edge cases

### Community 81 - "SemgrepParser"
Cohesion: 0.17
Nodes (3): SemgrepParser, {closure#2}(), SemgrepParserTest

### Community 83 - "Testing Strategy"
Cohesion: 0.33
Nodes (6): requesting-code-review skill, Self-review checklist, Definition of Done checklist, Testing Strategy, SCA test scenarios, Testing database and Redis safeguards

### Community 85 - "UnresolvedReason"
Cohesion: 0.14
Nodes (9): UnresolvedReason, LockfileUnreadable, MissingVersion, NonRegistrySource, RangeWithoutLockfile, UnsupportedLockfile, PackageJsonParser, {closure#1}() (+1 more)

### Community 87 - "Fixture body-parser"
Cohesion: 0.40
Nodes (5): integrity, resolved, version, packages, node_modules/body-parser

### Community 88 - "Fixture fsevents"
Cohesion: 0.40
Nodes (5): integrity, optional, resolved, version, node_modules/fsevents

### Community 89 - "Fixture jest"
Cohesion: 0.40
Nodes (5): dev, integrity, resolved, version, node_modules/jest

### Community 90 - "Fixture jest-util"
Cohesion: 0.40
Nodes (5): dev, integrity, resolved, version, node_modules/jest-util

### Community 91 - "Fixture ms"
Cohesion: 0.40
Nodes (5): devOptional, integrity, resolved, version, node_modules/ms

### Community 92 - "Decisions & Requirements"
Cohesion: 0.40
Nodes (5): Technical Decisions, Open decisions D-02..D-24, Proposed decisions D-06, D-11, D-15, D-31, Roadmap TAHAP 0-11, Functional Requirements FR-01..FR-10

### Community 94 - "Fixture express"
Cohesion: 0.50
Nodes (4): integrity, resolved, version, node_modules/express

### Community 95 - "Fixture qs"
Cohesion: 0.50
Nodes (4): integrity, resolved, version, node_modules/qs

### Community 96 - "Fixture scoped pkg"
Cohesion: 0.50
Nodes (4): integrity, resolved, version, node_modules/@scope/pkg

### Community 97 - "Fixture nested qs"
Cohesion: 0.50
Nodes (4): integrity, resolved, version, node_modules/@scope/pkg/node_modules/qs

### Community 115 - "rules"
Cohesion: 0.13
Nodes (15): category, cwe, owasp, pattern, pattern, pattern-either, pattern-not, rules (+7 more)

### Community 117 - "MetricsParser"
Cohesion: 0.16
Nodes (3): MetricsParser, MetricsReport, MetricsTest

### Community 119 - "AppServiceProvider.php"
Cohesion: 0.09
Nodes (7): AppServiceProvider, {closure#2}(), {closure#3}(), DnsHostResolver, FindingNormalizer, NormalizedFindings, SnippetReader

### Community 121 - ".row"
Cohesion: 0.34
Nodes (3): FunctionMetrics, MetricValues, {closure#2}()

### Community 130 - "SastToolExecutor"
Cohesion: 0.36
Nodes (3): MetricsRunner, SastToolExecutor, SemgrepRunner

## Ambiguous Edges - Review These
- `robots.txt` → `Backend Laravel README`  [AMBIGUOUS]
  backend/public/robots.txt · relation: conceptually_related_to
- `Backend Laravel README` → `Project README`  [AMBIGUOUS]
  backend/README.md · relation: conceptually_related_to

## Knowledge Gaps
- **110 isolated node(s):** `Completed`, `Failed`, `Skipped`, `File`, `Function` (+105 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 665 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **62 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `TestCase` connect `API Feature Tests` to `Audit Dependencies Tests`, `RepositoryFileWalker`, `Users Table & DB Tests`, `AuditWorkspaceTest`, `AI Tables Tests`, `RedisConnectionTest`, `OSV Client Tests`, `AuditScan`, `AiRefactoring Model`, `ScaStageRunner Tests`, `ProcessAuditJobTest.php`, `OSV Client Test Closures`, `ProcessAuditJob & Store Tests`, `Audit Status Manager`, `OSV Details Tests`, `AuditController.php`, `Web Routes & API Errors`, `ScaStageRunner Closures`, `Vulnerability Store Tests`, `Git Cloner Tests`, `Safe Zip Extractor Tests`, `SourceType`, `Store Audit Tests`, `Stage Status Enum`, `Dependency Store Tests`, `Zip Extractor Closures`, `SastStageRunnerTest.php`, `Show Audit Tests`, `OSV Query & Live Test`, `Result Model Tests`, `App Config Tests`, `AuditScan Model Tests`, `SastStageRunnerTest`, `MetricsRunnerTest`, `SemgrepRunnerTest`, `SastResultStoreTest`, `EslintRunnerTest`?**
  _High betweenness centrality (0.198) - this node is a cross-community bridge._
- **What connects `Completed`, `Failed`, `Skipped` to the rest of the system?**
  _110 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Audit Dependencies Tests` be split into smaller, more focused modules?**
  _Cohesion score 0.0698247138925105 - nodes in this community are weakly interconnected._
- **Why does `AuditScan` connect `AuditScan` to `Audit Dependencies Tests`, `API Feature Tests`, `RepositoryIngestor.php`, `AiRefactoring Model`, `ScaStageRunner Tests`, `AuditScan Model`, `ProcessAuditJobTest.php`, `Code Language & Metric Scope`, `Dependency Relation Enums`, `Audit Status Enum`, `Audit Stage Enum`, `ProcessAuditJob & Store Tests`, `Audit Status Manager`, `AuditController.php`, `ScaStageRunner Closures`, `Illuminate\Http\Request`, `Vulnerability Store Tests`, `Git Cloner Tests`, `SourceType`, `Vulnerability Store`, `Stage Status Enum`, `Dependency & Health Controllers`, `List Request Validation`, `OSV Client Core`, `Dependency Store Tests`, `SastStageRunnerTest.php`, `StageResult`, `SastStageRunnerTest`, `AuditWorkspace`, `AuditVulnerabilityController.php`, `MetricsRunnerTest`, `.row`, `SastResultStoreTest`, `SastStageRunner`?**
  _High betweenness centrality (0.160) - this node is a cross-community bridge._
- **Should `API Feature Tests` be split into smaller, more focused modules?**
  _Cohesion score 0.058384547848990345 - nodes in this community are weakly interconnected._

### Low-confidence Hints
_AMBIGUOUS edges — the extractor was unsure. Verify before acting on these._

- **What is the exact relationship between `robots.txt` and `Backend Laravel README`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._
- **What is the exact relationship between `Backend Laravel README` and `Project README`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._