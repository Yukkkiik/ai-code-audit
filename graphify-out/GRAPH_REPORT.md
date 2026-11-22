# Graph Report - ai-code-security-audit-platform  (2026-11-22)

## Corpus Check
- 235 files · ~77,339 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 10 file(s) not represented in the graph (top: (none) 7, .xml 1, .ico 1)

## Summary
- 2031 nodes · 4930 edges · 109 communities (66 shown, 43 thin omitted)
- Extraction: 97% EXTRACTED · 3% INFERRED · 0% AMBIGUOUS · INFERRED: 156 edges (avg confidence: 0.84)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Audit Dependencies Tests
- API Feature Tests
- File Walker & Manifest Failure
- Users Table & DB Tests
- Provider & URL Exceptions
- Fix Match Reasons
- Composer Dependencies
- AI Tables Tests
- Package Lock Parser Tests
- OSV Client Tests
- Database Migrations
- Vulnerability Controller
- AiRefactoring Model
- ScaStageRunner Tests
- AuditScan Model
- Audit Job & Workspace
- OSV Client Test Closures
- Code Language & Metric Scope
- Dependency Relation Enums
- Fix Status & Version Change
- Audit Status Enum
- Semver Version
- Audit Stage Enum
- Ecosystem & Finding Category
- Package Json Parser Tests
- ProcessAuditJob & Store Tests
- Dependency Scope
- Audit Status Manager
- OSV Details Tests
- Config & Workspace Tests
- Manifest Type & Lock Parser
- OSV Failure Reasons
- Audit Controller
- Web Routes & API Errors
- Dependency Collector
- Frontend Build Packages
- ScaStageRunner Closures
- OSV Parser Tests
- Vulnerability Severity
- Audit Collections
- Audit & Dependency Resources
- Fixed Version Matcher
- Vulnerability Store Tests
- Git Cloner Tests
- Manifest JSON Reader
- Source Type & Scan Creator
- Safe Zip Extractor Tests
- AI Source & Status
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
- ScaStageRunner & Store
- Zip Extractor Closures
- Safe Zip Extractor
- OSV Vulnerability Parser
- Store Request & NoStore
- OSV Query Result
- Show Audit Tests
- OSV Details Closures
- API Contract & Roadmap
- Exact Version Parsing
- CLAUDE.md Working Rules
- OSV Query & Live Test
- Result Model Tests
- AI Guardrail & State Machine
- SCA Audit Concepts
- SCA Endpoints & Roadmap Items
- API Exception Renderer
- Dependency Model & Store
- App Config Tests
- Lockfile v2 Fixture
- Queue Probe Jobs
- Repo Sandbox Safety
- Ingestor Tests
- AuditScan Model Tests
- Testing Strategy
- Database Connection Tests
- Redis Connection Tests
- Fixture body-parser
- Fixture fsevents
- Fixture jest
- Fixture jest-util
- Fixture ms
- Decisions & Requirements
- Fixture express
- Fixture qs
- Fixture scoped pkg
- Fixture nested qs
- Systematic Debugging
- AI Remediation Reporting

## God Nodes (most connected - your core abstractions)
1. `AuditScan` - 124 edges
2. `TestCase` - 74 edges
3. `OsvClientTest` - 57 edges
4. `PackageLockParserTest` - 52 edges
5. `FixedVersionMatcherTest` - 47 edges
6. `StageStatus` - 45 edges
7. `DependencyCollectorTest` - 43 edges
8. `ScaStageRunnerTest` - 39 edges
9. `AuditStatus` - 36 edges
10. `OsvFailureReason` - 35 edges

## Surprising Connections (you probably didn't know these)
- `sast-ast-rules skill` --references--> `TAHAP 6 SAST Engine (not started)`  [INFERRED]
  .claude/skills/sast-ast-rules/SKILL.md → IMPLEMENTATION_ROADMAP.md
- `Backend Laravel README` --conceptually_related_to--> `Project README`  [AMBIGUOUS]
  backend/README.md → README.md
- `ai-remediation-guardrail skill` --references--> `fix_match object`  [INFERRED]
  .claude/skills/ai-remediation-guardrail/SKILL.md → API_CONTRACT.md
- `requesting-code-review skill` --implements--> `Plan-First workflow (FASE A/B)`  [INFERRED]
  .claude/skills/requesting-code-review/SKILL.md → CLAUDE.md
- `sca-dependency-audit skill` --references--> `TAHAP 7 SCA Engine`  [EXTRACTED]
  .claude/skills/sca-dependency-audit/SKILL.md → IMPLEMENTATION_ROADMAP.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Repository ingestion safety controls** — claude_skills_repo_sandbox_safety_skill_zip_slip_prevention, claude_skills_repo_sandbox_safety_skill_ssrf_protection, claude_skills_repo_sandbox_safety_skill_safe_git_clone, claude_skills_repo_sandbox_safety_skill_workspace_cleanup [EXTRACTED 0.85]
- **SCA pipeline (collect, store, OSV query, store vulnerabilities, fix match)** — decisions_a30_dependency_collector, decisions_a31_osv_client, decisions_a32_sca_stage_runner, decisions_a34_fixed_version_matcher [EXTRACTED 0.85]
- **AI guardrail pattern** — claude_skills_ai_remediation_guardrail_skill_prompt_injection_defense, claude_skills_ai_remediation_guardrail_skill_structured_output_validation, claude_skills_ai_remediation_guardrail_skill_static_template_fallback, claude_skills_ai_remediation_guardrail_skill_advisory_notice [EXTRACTED 0.85]

## Communities (109 total, 43 thin omitted)

### Community 0 - "Audit Dependencies Tests"
Cohesion: 0.12
Nodes (4): AuditDependenciesTest, AuditVulnerabilitiesTest, AuditVulnerabilityFixMatchTest, BuildsScaResults

### Community 1 - "API Feature Tests"
Cohesion: 0.07
Nodes (10): {closure#1}(), {closure#1}(), HealthCheckTest, ListAuditsTest, AuditScansTableTest, DatabaseSchemaTest, ExampleTest, QueueConnectionTest (+2 more)

### Community 2 - "File Walker & Manifest Failure"
Cohesion: 0.06
Nodes (10): RepositoryFileWalker, ManifestFailure, {closure#1}(), ManifestFinder, ManifestProject, EnvExampleTest, ExampleTest, RepositoryFileWalkerTest (+2 more)

### Community 4 - "Provider & URL Exceptions"
Cohesion: 0.06
Nodes (8): InvalidRepositoryUrlException, AppServiceProvider, DnsHostResolver, GitUrlValidator, HostResolver, {closure#1}(), FakeHostResolver, GitUrlValidatorTest

### Community 5 - "Fix Match Reasons"
Cohesion: 0.15
Nodes (7): FixMatchReason, DetailUnavailable, InvalidVersion, NoRangeData, NotInListedRanges, UnevaluableRange, FixedVersionMatcherTest

### Community 6 - "Composer Dependencies"
Cohesion: 0.04
Nodes (47): pestphp/pest-plugin, php-http/discovery, autoload, autoload-dev, psr-4, psr-4, config, allow-plugins (+39 more)

### Community 7 - "AI Tables Tests"
Cohesion: 0.10
Nodes (3): AiTablesTest, SastTablesTest, ScaTablesTest

### Community 10 - "Database Migrations"
Cohesion: 0.07
Nodes (10): {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}() (+2 more)

### Community 11 - "Vulnerability Controller"
Cohesion: 0.09
Nodes (11): AuditVulnerabilityController, VulnerabilityCollection, AuditScan, AuditScanPolicy, AuditAccessGuard, CreatedAuditScan, ScaCoverage, ScaScanSummary (+3 more)

### Community 12 - "AiRefactoring Model"
Cohesion: 0.12
Nodes (6): AiRefactoring, AiUsageLog, CodeMetric, ScanFinding, ScanToolRun, Vulnerability

### Community 14 - "AuditScan Model"
Cohesion: 0.08
Nodes (5): {closure#1}(), {closure#2}(), User, AuditScanFactory, DatabaseSeeder

### Community 15 - "Audit Job & Workspace"
Cohesion: 0.13
Nodes (4): AuditWorkspace, RepositoryIngestor, ingest(), PackageLockParserNoNetworkTest

### Community 16 - "OSV Client Test Closures"
Cohesion: 0.08
Nodes (15): {closure#11}(), {closure#15}(), {closure#17}(), {closure#18}(), {closure#2}(), {closure#20}(), {closure#22}(), {closure#23}() (+7 more)

### Community 17 - "Code Language & Metric Scope"
Cohesion: 0.08
Nodes (15): CodeLanguage, Javascript, Typescript, MetricScope, File, Function, ToolRunStatus, Completed (+7 more)

### Community 18 - "Dependency Relation Enums"
Cohesion: 0.09
Nodes (16): DependencyRelation, Direct, Transitive, UnresolvedReason, LockfileUnreadable, MissingVersion, NonRegistrySource, RangeWithoutLockfile (+8 more)

### Community 19 - "Fix Status & Version Change"
Cohesion: 0.09
Nodes (10): FixMatchStatus, FixAvailable, NoFixRecorded, Undetermined, VersionChange, Major, Minor, Patch (+2 more)

### Community 20 - "Audit Status Enum"
Cohesion: 0.13
Nodes (15): AuditStatus, Completed, Failed, Ingesting, Partial, Queued, Scanning, InvalidAuditTransitionException (+7 more)

### Community 21 - "Semver Version"
Cohesion: 0.11
Nodes (4): {closure#3}(), {closure#8}(), {closure#4}(), SemverVersionTest

### Community 22 - "Audit Stage Enum"
Cohesion: 0.13
Nodes (9): AuditStage, Sast, Sca, {closure#2}(), AuditStageRunners, StageRunner, UnavailableStageRunner, IngestedRepository (+1 more)

### Community 23 - "Ecosystem & Finding Category"
Cohesion: 0.08
Nodes (20): Ecosystem, Npm, FindingCategory, BestPractice, Quality, Security, FindingTool, Eslint (+12 more)

### Community 25 - "ProcessAuditJob & Store Tests"
Cohesion: 0.13
Nodes (3): ProcessAuditJob, {closure#1}(), ProcessAuditJobTest

### Community 26 - "Dependency Scope"
Cohesion: 0.15
Nodes (7): DependencyScope, Development, Optional, Peer, Production, PackageJsonParser, ParsedDependency

### Community 30 - "Manifest Type & Lock Parser"
Cohesion: 0.14
Nodes (6): ManifestType, PackageJson, PackageLockV2, PackageLockV3, PackageLockParser, ParsedLockfile

### Community 31 - "OSV Failure Reasons"
Cohesion: 0.12
Nodes (10): OsvFailureReason, ClientError, InvalidResponse, PaginationIncomplete, RateLimited, ServerError, TimeBudgetExhausted, Timeout (+2 more)

### Community 32 - "Audit Controller"
Cohesion: 0.13
Nodes (4): AuditController, {closure#1}(), StoreAuditRequest, ValidatedGitUrl

### Community 33 - "Web Routes & API Errors"
Cohesion: 0.08
Nodes (3): GET /(), ApiErrorFormatTest, POST /validation()

### Community 34 - "Dependency Collector"
Cohesion: 0.13
Nodes (4): CollectedDependencies, {closure#1}(), {closure#2}(), DependencyCollector

### Community 35 - "Frontend Build Packages"
Cohesion: 0.13
Nodes (20): devDependencies, concurrently@^10.0.3, laravel-vite-plugin@^3.1, tailwindcss@^4.0.0, @tailwindcss/vite@^4.0.0, vite@^8.0.0, optionalDependencies, @laravel/multiplex@^0.4.1 (+12 more)

### Community 36 - "ScaStageRunner Closures"
Cohesion: 0.15
Nodes (11): {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#16}(), {closure#17}(), {closure#18}(), {closure#3}() (+3 more)

### Community 38 - "Vulnerability Severity"
Cohesion: 0.12
Nodes (7): VulnerabilitySeverity, Critical, High, Low, Medium, Unknown, OsvVulnerabilityDetail

### Community 39 - "Audit Collections"
Cohesion: 0.14
Nodes (7): AuditCollection, DependencyCollection, ScaListCollection, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}()

### Community 40 - "Audit & Dependency Resources"
Cohesion: 0.16
Nodes (4): AuditResource, DependencyResource, VulnerabilityDetailResource, VulnerabilityResource

### Community 41 - "Fixed Version Matcher"
Cohesion: 0.17
Nodes (5): {closure#2}(), {closure#3}(), FixedVersionMatcher, SemverVersion, {closure#4}()

### Community 43 - "Git Cloner Tests"
Cohesion: 0.19
Nodes (4): {closure#2}(), {closure#3}(), GitRepositoryClonerTest, FakeGitBinary

### Community 44 - "Manifest JSON Reader"
Cohesion: 0.16
Nodes (3): ManifestParseException, ManifestJsonReader, ManifestJsonReaderTest

### Community 45 - "Source Type & Scan Creator"
Cohesion: 0.18
Nodes (5): SourceType, Git, Zip, AuditScanCreator, AuditScanCreatorTest

### Community 47 - "AI Source & Status"
Cohesion: 0.12
Nodes (9): AiSource, Llm, StaticTemplate, AiStatus, Completed, Failed, Pending, RejectedValidation (+1 more)

### Community 48 - "Vulnerability Store"
Cohesion: 0.16
Nodes (6): OsvVulnerabilityRef, VulnerabilityMatch, {closure#1}(), {closure#3}(), VulnerabilityStore, {closure#1}()

### Community 50 - "SAST & AI Schema Concepts"
Cohesion: 0.21
Nodes (17): Metrics: Cyclomatic Complexity, Halstead, MI, sast-ast-rules skill, Finding normalization, Health score (no SCA), Severity mapping per tool, ai_refactorings table, ai_usage_logs table (atomic daily quota), audit_scans table (+9 more)

### Community 51 - "Stage Status Enum"
Cohesion: 0.18
Nodes (8): StageStatus, Completed, Failed, Partial, Pending, Running, Skipped, StageResult

### Community 53 - "Dependency & Health Controllers"
Cohesion: 0.16
Nodes (3): AuditDependencyController, HealthController, Controller

### Community 54 - "List Request Validation"
Cohesion: 0.17
Nodes (4): ListAuditChildrenRequest, ListAuditsRequest, ListDependenciesRequest, ListVulnerabilitiesRequest

### Community 55 - "OSV Client Retry"
Cohesion: 0.21
Nodes (5): {closure#2}(), {closure#3}(), {closure#5}(), {closure#6}(), {closure#7}()

### Community 58 - "Architecture & Auth Concepts"
Cohesion: 0.14
Nodes (14): Authentication and audit access rules, Architecture, Guest Token (X-Guest-Token, hashed), Backend Module Breakdown (Services/*), ProcessAuditJob orchestrator, StageRunner interface, Tech Stack (Next.js, Laravel, MySQL, Redis, Semgrep, ESLint, OSV.dev, LLM), Backend Laravel README (+6 more)

### Community 60 - "Zip Extractor Closures"
Cohesion: 0.21
Nodes (9): {closure#10}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+1 more)

### Community 63 - "Store Request & NoStore"
Cohesion: 0.23
Nodes (3): NoStoreResponse, {closure#1}(), {closure#2}()

### Community 66 - "OSV Details Closures"
Cohesion: 0.27
Nodes (8): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}()

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

### Community 76 - "Dependency Model & Store"
Cohesion: 0.25
Nodes (3): Dependency, {closure#2}(), {closure#1}()

### Community 78 - "Lockfile v2 Fixture"
Cohesion: 0.22
Nodes (8): dependencies, legacy-only, resolved, version, lockfileVersion, name, requires, version

### Community 80 - "Repo Sandbox Safety"
Cohesion: 0.38
Nodes (7): Architecture Security Principles, Non-negotiable security rules, repo-sandbox-safety skill, Safe git clone via Symfony Process, Git URL SSRF protection, ZIP Slip prevention (manual entry extraction), Ingestion edge cases

### Community 83 - "Testing Strategy"
Cohesion: 0.33
Nodes (6): requesting-code-review skill, Self-review checklist, Definition of Done checklist, Testing Strategy, SCA test scenarios, Testing database and Redis safeguards

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

## Ambiguous Edges - Review These
- `Project README` → `Backend Laravel README`  [AMBIGUOUS]
  backend/README.md · relation: conceptually_related_to
- `Backend Laravel README` → `robots.txt`  [AMBIGUOUS]
  backend/public/robots.txt · relation: conceptually_related_to

## Knowledge Gaps
- **106 isolated node(s):** `Llm`, `StaticTemplate`, `Pending`, `Completed`, `Failed` (+101 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 559 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **43 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `TestCase` connect `API Feature Tests` to `Audit Dependencies Tests`, `Users Table & DB Tests`, `AI Tables Tests`, `OSV Client Tests`, `AiRefactoring Model`, `ScaStageRunner Tests`, `Audit Job & Workspace`, `OSV Client Test Closures`, `Dependency Relation Enums`, `Audit Status Enum`, `Ecosystem & Finding Category`, `ProcessAuditJob & Store Tests`, `Audit Status Manager`, `OSV Details Tests`, `Config & Workspace Tests`, `Web Routes & API Errors`, `ScaStageRunner Closures`, `Vulnerability Store Tests`, `Git Cloner Tests`, `Source Type & Scan Creator`, `Safe Zip Extractor Tests`, `Store Audit Tests`, `Dependency Store Tests`, `Zip Extractor Closures`, `Show Audit Tests`, `OSV Details Closures`, `OSV Query & Live Test`, `Result Model Tests`, `App Config Tests`, `Ingestor Tests`, `AuditScan Model Tests`, `Database Connection Tests`, `Redis Connection Tests`?**
  _High betweenness centrality (0.180) - this node is a cross-community bridge._
- **What connects `Llm`, `StaticTemplate`, `Pending` to the rest of the system?**
  _106 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Audit Dependencies Tests` be split into smaller, more focused modules?**
  _Cohesion score 0.12216216216216216 - nodes in this community are weakly interconnected._
- **Why does `AuditScan` connect `Vulnerability Controller` to `Audit Dependencies Tests`, `API Feature Tests`, `Provider & URL Exceptions`, `AiRefactoring Model`, `ScaStageRunner Tests`, `AuditScan Model`, `Audit Job & Workspace`, `Code Language & Metric Scope`, `Dependency Relation Enums`, `Audit Status Enum`, `Audit Stage Enum`, `Ecosystem & Finding Category`, `ProcessAuditJob & Store Tests`, `Audit Status Manager`, `Config & Workspace Tests`, `Audit Controller`, `ScaStageRunner Closures`, `Audit & Dependency Resources`, `Vulnerability Store Tests`, `Source Type & Scan Creator`, `Vulnerability Store`, `Stage Status Enum`, `Dependency & Health Controllers`, `List Request Validation`, `Dependency Store Tests`, `ScaStageRunner & Store`, `Dependency Model & Store`, `Ingestor Tests`?**
  _High betweenness centrality (0.158) - this node is a cross-community bridge._
- **Should `API Feature Tests` be split into smaller, more focused modules?**
  _Cohesion score 0.0677555958862674 - nodes in this community are weakly interconnected._

### Low-confidence Hints
_AMBIGUOUS edges — the extractor was unsure. Verify before acting on these._

- **What is the exact relationship between `Project README` and `Backend Laravel README`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._
- **What is the exact relationship between `Backend Laravel README` and `robots.txt`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._