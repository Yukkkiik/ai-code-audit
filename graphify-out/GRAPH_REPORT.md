# Graph Report - ai-code-security-audit-platform  (2026-11-23)

## Corpus Check
- 303 files · ~105,523 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 10 file(s) not represented in the graph (top: (none) 7, .xml 1, .ico 1)

## Summary
- 2706 nodes · 6863 edges · 135 communities (78 shown, 57 thin omitted)
- Extraction: 97% EXTRACTED · 3% INFERRED · 0% AMBIGUOUS · INFERRED: 218 edges (avg confidence: 0.84)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `80127d92`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- .dependency
- API Feature Tests
- RepositoryFileWalker
- Users Table & DB Tests
- ExplicitVersionsMatchTest
- Fix Match Reasons
- Composer Dependencies
- AiTablesTest
- Package Lock Parser Tests
- OSV Client Tests
- Database Migrations
- AuditScan
- Vulnerability
- ScaStageRunner Tests
- AuditScan Model
- ProcessAuditJobTest.php
- OSV Client Test Closures
- Code Language & Metric Scope
- Dependency Relation Enums
- Fix Status & Version Change
- Audit Status Enum
- .tryParse
- AuditStage
- FindingTool
- Package Json Parser Tests
- ProcessAuditJob & Store Tests
- metrics.mjs
- Audit Status Manager
- OSV Details Tests
- .guestAudit
- Manifest Type & Lock Parser
- OsvFailureReason
- SourceType
- Web Routes & API Errors
- DependencyCollectorTest.php
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
- IngestionException
- AuditResultModelsTest.php
- Vulnerability Store
- StoreAuditTest
- SAST & AI Schema Concepts
- Stage Status Enum
- .token
- AuditController.php
- MetricScope
- OsvClient
- Illuminate\Support\Carbon
- Dependency Store Tests
- Architecture & Auth Concepts
- AuditVulnerabilityCombinedFixTest
- AiRefactoringFactory.php
- AuditDependencyController.php
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
- BuildsScaResults
- Repo Sandbox Safety
- SemgrepParser
- AuditScan Model Tests
- Testing Strategy
- AuditVulnerabilityFixMatchTest
- CombinedFixMatchTest
- Fixture body-parser
- Fixture fsevents
- Fixture jest
- Fixture jest-util
- Fixture ms
- Decisions & Requirements
- AuditVulnerabilityExplicitVersionsTest
- Fixture express
- Fixture qs
- Fixture scoped pkg
- Fixture nested qs
- Systematic Debugging
- AI Remediation Reporting
- SemverVersionTest
- SastStageRunnerTest
- AuditWorkspace
- AuditVulnerabilityController.php
- FindingNormalizerTest
- MetricsRunnerTest
- rules
- SemgrepRunnerTest
- MetricsParser
- Illuminate\Http\Resources\Json\JsonResource
- AppServiceProvider.php
- ApiErrorFormatTest.php
- .row
- ScaTablesTest
- ManifestFailure
- EslintRunnerTest
- PHPUnit\Framework\TestCase
- VulnerabilitySeverity
- SastStageRunner
- .show
- VulnerabilityDetailResource
- ScanFinding
- UsersTableTest
- DatabaseConnectionTest
- User

## God Nodes (most connected - your core abstractions)
1. `AuditScan` - 150 edges
2. `TestCase` - 90 edges
3. `FixMatchReason` - 62 edges
4. `StageStatus` - 58 edges
5. `OsvClientTest` - 57 edges
6. `PackageLockParserTest` - 52 edges
7. `FixedVersionMatcherTest` - 47 edges
8. `ExplicitVersionsMatchTest` - 45 edges
9. `DependencyCollectorTest` - 43 edges
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

## Communities (135 total, 57 thin omitted)

### Community 0 - ".dependency"
Cohesion: 0.23
Nodes (3): AuditVulnerabilitiesTest, {closure#1}(), {closure#2}()

### Community 1 - "API Feature Tests"
Cohesion: 0.08
Nodes (10): {closure#1}(), HealthCheckTest, ListAuditsTest, AuditScansTableTest, DatabaseSchemaTest, ExampleTest, QueueConnectionTest, SastIgnoredDirectoriesTest (+2 more)

### Community 2 - "RepositoryFileWalker"
Cohesion: 0.06
Nodes (10): {closure#1}(), RepositoryFileWalker, JsTsFileSelector, JsTsSelection, {closure#1}(), ManifestFinder, ManifestProject, RepositoryFileWalkerTest (+2 more)

### Community 5 - "Fix Match Reasons"
Cohesion: 0.13
Nodes (7): FixMatchReason, DetailUnavailable, InvalidVersion, NoRangeData, NotInListedRanges, UnevaluableRange, FixedVersionMatcherTest

### Community 6 - "Composer Dependencies"
Cohesion: 0.04
Nodes (47): pestphp/pest-plugin, php-http/discovery, autoload, autoload-dev, psr-4, psr-4, config, allow-plugins (+39 more)

### Community 9 - "OSV Client Tests"
Cohesion: 0.11
Nodes (8): {closure#15}(), {closure#17}(), {closure#20}(), {closure#22}(), {closure#23}(), {closure#4}(), {closure#7}(), OsvClientTest

### Community 10 - "Database Migrations"
Cohesion: 0.07
Nodes (12): {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}() (+4 more)

### Community 11 - "AuditScan"
Cohesion: 0.11
Nodes (9): AuditScan, AuditScanPolicy, CreatedAuditScan, {closure#1}(), SastScanSummary, {closure#2}(), {closure#2}(), {closure#1}() (+1 more)

### Community 12 - "Vulnerability"
Cohesion: 0.16
Nodes (4): AiRefactoring, AiUsageLog, CodeMetric, Vulnerability

### Community 14 - "AuditScan Model"
Cohesion: 0.16
Nodes (3): {closure#1}(), {closure#2}(), AuditScanFactory

### Community 16 - "OSV Client Test Closures"
Cohesion: 0.10
Nodes (8): {closure#11}(), {closure#18}(), {closure#2}(), {closure#24}(), {closure#3}(), {closure#5}(), {closure#8}(), {closure#9}()

### Community 17 - "Code Language & Metric Scope"
Cohesion: 0.11
Nodes (7): AiUsageLogFactory, CodeMetricFactory, DependencyFactory, ScanFindingFactory, ScanToolRunFactory, UserFactory, VulnerabilityFactory

### Community 18 - "Dependency Relation Enums"
Cohesion: 0.08
Nodes (22): DependencyRelation, Direct, Transitive, DependencyScope, Development, Optional, Peer, Production (+14 more)

### Community 19 - "Fix Status & Version Change"
Cohesion: 0.09
Nodes (12): FixMatchStatus, FixAvailable, NoFixRecorded, Undetermined, VersionChange, Major, Minor, Patch (+4 more)

### Community 20 - "Audit Status Enum"
Cohesion: 0.13
Nodes (15): AuditStatus, Completed, Failed, Ingesting, Partial, Queued, Scanning, InvalidAuditTransitionException (+7 more)

### Community 21 - ".tryParse"
Cohesion: 0.08
Nodes (5): {closure#1}(), {closure#4}(), {closure#10}(), {closure#11}(), {closure#7}()

### Community 22 - "AuditStage"
Cohesion: 0.10
Nodes (10): AuditStage, Sast, Sca, StageResult, StageRunner, UnavailableStageRunner, IngestedRepository, DependencyStore (+2 more)

### Community 23 - "FindingTool"
Cohesion: 0.07
Nodes (19): FindingCategory, BestPractice, Quality, Security, Severity, Critical, High, Info (+11 more)

### Community 25 - "ProcessAuditJob & Store Tests"
Cohesion: 0.09
Nodes (6): ProcessAuditJob, AuditStageRunners, {closure#1}(), ProcessAuditJobTest, FailingProbeJob, QueueProbeJob

### Community 26 - "metrics.mjs"
Cohesion: 0.08
Nodes (27): analyze(), CLOSING_KINDS, DECISION_KINDS, decisions(), FUNCTION_KINDS, functionName(), halstead(), IGNORED_KINDS (+19 more)

### Community 29 - ".guestAudit"
Cohesion: 0.14
Nodes (10): FindingTool, Eslint, Semgrep, ToolRunStatus, Completed, Failed, Skipped, AuditFindingsTest (+2 more)

### Community 30 - "Manifest Type & Lock Parser"
Cohesion: 0.11
Nodes (6): ManifestType, PackageJson, PackageLockV2, PackageLockV3, PackageLockParser, ParsedLockfile

### Community 31 - "OsvFailureReason"
Cohesion: 0.10
Nodes (11): OsvFailureReason, ClientError, InvalidResponse, PaginationIncomplete, RateLimited, ServerError, TimeBudgetExhausted, Timeout (+3 more)

### Community 32 - "SourceType"
Cohesion: 0.19
Nodes (5): SourceType, Git, Zip, AuditScanCreator, AuditScanCreatorTest

### Community 34 - "DependencyCollectorTest.php"
Cohesion: 0.13
Nodes (4): CollectedDependencies, {closure#1}(), {closure#2}(), DependencyCollector

### Community 35 - "Frontend Build Packages"
Cohesion: 0.13
Nodes (20): devDependencies, concurrently@^10.0.3, laravel-vite-plugin@^3.1, tailwindcss@^4.0.0, @tailwindcss/vite@^4.0.0, vite@^8.0.0, optionalDependencies, @laravel/multiplex@^0.4.1 (+12 more)

### Community 36 - "ScaStageRunner Closures"
Cohesion: 0.11
Nodes (19): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+11 more)

### Community 39 - "SastListCollection"
Cohesion: 0.18
Nodes (4): AuditCollection, SastListCollection, ScaListCollection, VulnerabilityCollection

### Community 40 - "Illuminate\Http\Request"
Cohesion: 0.27
Nodes (4): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}()

### Community 41 - "Fixed Version Matcher"
Cohesion: 0.11
Nodes (6): AdvisoryAffects, {closure#2}(), {closure#3}(), FixedVersionMatcher, SemverVersion, {closure#4}()

### Community 43 - "Git Cloner Tests"
Cohesion: 0.14
Nodes (5): {closure#2}(), {closure#3}(), GitRepositoryClonerTest, RepositoryIngestorTest, FakeGitBinary

### Community 44 - "Manifest JSON Reader"
Cohesion: 0.16
Nodes (3): ManifestParseException, ManifestJsonReader, ManifestJsonReaderTest

### Community 45 - "HealthCategory"
Cohesion: 0.13
Nodes (7): HealthCategory, Fair, Good, Poor, HealthScore, HealthScoreCalculator, HealthScoreCalculatorTest

### Community 46 - "IngestionException"
Cohesion: 0.07
Nodes (13): IngestionException, GitRepositoryCloner, SafeZipExtractor, {closure#10}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}() (+5 more)

### Community 47 - "AuditResultModelsTest.php"
Cohesion: 0.14
Nodes (7): Ecosystem, Npm, OsvQueryStatus, Failed, NotQueried, Queried, Dependency

### Community 48 - "Vulnerability Store"
Cohesion: 0.16
Nodes (6): OsvVulnerabilityRef, VulnerabilityMatch, {closure#1}(), {closure#3}(), VulnerabilityStore, {closure#1}()

### Community 49 - "StoreAuditTest"
Cohesion: 0.06
Nodes (6): HostResolver, StoreAuditTest, AuditWorkspaceTest, BuildsZipArchives, FakeHostResolver, UsesTemporaryAuditStorage

### Community 50 - "SAST & AI Schema Concepts"
Cohesion: 0.21
Nodes (17): Metrics: Cyclomatic Complexity, Halstead, MI, sast-ast-rules skill, Finding normalization, Health score (no SCA), Severity mapping per tool, ai_refactorings table, ai_usage_logs table (atomic daily quota), audit_scans table (+9 more)

### Community 51 - "Stage Status Enum"
Cohesion: 0.24
Nodes (8): StageStatus, Completed, Failed, Partial, Pending, Running, Skipped, SastScanSummaryTest

### Community 53 - "AuditController.php"
Cohesion: 0.08
Nodes (10): AuditController, {closure#1}(), AuditDependencyController, AuditFindingController, AuditMetricController, HealthController, Controller, FindingCollection (+2 more)

### Community 54 - "MetricScope"
Cohesion: 0.08
Nodes (11): MetricScope, File, Function, ListAuditChildrenRequest, ListAuditsRequest, ListDependenciesRequest, ListFindingsRequest, ListMetricsRequest (+3 more)

### Community 55 - "OsvClient"
Cohesion: 0.10
Nodes (7): OsvRequestException, {closure#2}(), {closure#3}(), {closure#5}(), {closure#6}(), {closure#7}(), OsvClient

### Community 58 - "Architecture & Auth Concepts"
Cohesion: 0.14
Nodes (14): Authentication and audit access rules, Architecture, Guest Token (X-Guest-Token, hashed), Backend Module Breakdown (Services/*), ProcessAuditJob orchestrator, StageRunner interface, Tech Stack (Next.js, Laravel, MySQL, Redis, Semgrep, ESLint, OSV.dev, LLM), Backend Laravel README (+6 more)

### Community 59 - "AuditVulnerabilityCombinedFixTest"
Cohesion: 0.10
Nodes (3): AuditVulnerabilityCombinedFixTest, {closure#1}(), {closure#2}()

### Community 60 - "AiRefactoringFactory.php"
Cohesion: 0.12
Nodes (9): AiSource, Llm, StaticTemplate, AiStatus, Completed, Failed, Pending, RejectedValidation (+1 more)

### Community 61 - "AuditDependencyController.php"
Cohesion: 0.17
Nodes (3): DependencyCollection, ScaCoverage, ScaScanSummary

### Community 63 - "StoreAuditRequest.php"
Cohesion: 0.06
Nodes (9): InvalidRepositoryUrlException, NoStoreResponse, {closure#1}(), {closure#2}(), StoreAuditRequest, GitUrlValidator, {closure#1}(), ValidatedGitUrl (+1 more)

### Community 64 - "SastStageRunnerTest.php"
Cohesion: 0.09
Nodes (8): MetricsRunner, SastToolExecutor, {closure#2}(), SemgrepRunner, {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}()

### Community 66 - "PHPUnit\Framework\Attributes\DataProvider"
Cohesion: 0.21
Nodes (5): CodeLanguage, Javascript, Typescript, FileMetrics, MetricsAggregate

### Community 67 - "API Contract & Roadmap"
Cohesion: 0.20
Nodes (11): Audit endpoints (POST/GET /audits), API Contract, Rate limit policy, AI Remediation endpoints, Report endpoints (JSON/PDF), Uniform error response format, Implementation Roadmap, TAHAP 6 SAST Engine (not started) (+3 more)

### Community 69 - "CLAUDE.md Working Rules"
Cohesion: 0.20
Nodes (11): Anti-AI-Slop rules, CLAUDE.md Working Rules, Git rules, Plan-First workflow (FASE A/B), brainstorming skill, Requirements exploration, test-driven-development skill, Red-Green-Refactor cycle (+3 more)

### Community 70 - "OSV Query & Live Test"
Cohesion: 0.22
Nodes (3): OsvQuery, {closure#1}(), OsvLiveTest

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
Cohesion: 0.11
Nodes (5): SastToolException, EslintParser, {closure#2}(), EslintRunner, EslintParserTest

### Community 78 - "Lockfile v2 Fixture"
Cohesion: 0.22
Nodes (8): dependencies, legacy-only, resolved, version, lockfileVersion, name, requires, version

### Community 79 - "BuildsScaResults"
Cohesion: 0.24
Nodes (4): AuditMetricsTest, {closure#1}(), {closure#2}(), BuildsScaResults

### Community 80 - "Repo Sandbox Safety"
Cohesion: 0.38
Nodes (7): Architecture Security Principles, Non-negotiable security rules, repo-sandbox-safety skill, Safe git clone via Symfony Process, Git URL SSRF protection, ZIP Slip prevention (manual entry extraction), Ingestion edge cases

### Community 81 - "SemgrepParser"
Cohesion: 0.12
Nodes (5): {closure#2}(), SastText, SemgrepParser, ToolReport, SemgrepParserTest

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

### Community 110 - "SastStageRunnerTest"
Cohesion: 0.10
Nodes (6): MetricsResult, ToolRunResult, {closure#1}(), SastResultStoreTest, run(), SastStageRunnerTest

### Community 115 - "rules"
Cohesion: 0.13
Nodes (15): category, cwe, owasp, pattern, pattern, pattern-either, pattern-not, rules (+7 more)

### Community 118 - "Illuminate\Http\Resources\Json\JsonResource"
Cohesion: 0.27
Nodes (3): DependencyResource, FindingResource, MetricResource

### Community 119 - "AppServiceProvider.php"
Cohesion: 0.12
Nodes (6): AppServiceProvider, {closure#2}(), {closure#3}(), {closure#5}(), DnsHostResolver, SnippetReader

### Community 121 - ".row"
Cohesion: 0.26
Nodes (4): FunctionMetrics, MetricValues, {closure#2}(), MetricsTest

### Community 125 - "PHPUnit\Framework\TestCase"
Cohesion: 0.16
Nodes (3): EnvExampleTest, ExampleTest, {closure#4}()

### Community 126 - "VulnerabilitySeverity"
Cohesion: 0.22
Nodes (6): VulnerabilitySeverity, Critical, High, Low, Medium, Unknown

## Ambiguous Edges - Review These
- `robots.txt` → `Backend Laravel README`  [AMBIGUOUS]
  backend/public/robots.txt · relation: conceptually_related_to
- `Backend Laravel README` → `Project README`  [AMBIGUOUS]
  backend/README.md · relation: conceptually_related_to

## Knowledge Gaps
- **110 isolated node(s):** `DetailUnavailable`, `NoRangeData`, `NotInListedRanges`, `UnevaluableRange`, `InvalidVersion` (+105 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 687 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **57 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `TestCase` connect `API Feature Tests` to `.dependency`, `ScanFinding`, `UsersTableTest`, `DatabaseConnectionTest`, `AiTablesTest`, `OSV Client Tests`, `Database Migrations`, `ScaStageRunner Tests`, `ProcessAuditJobTest.php`, `OSV Client Test Closures`, `Dependency Relation Enums`, `ProcessAuditJob & Store Tests`, `Audit Status Manager`, `OSV Details Tests`, `.guestAudit`, `SourceType`, `Web Routes & API Errors`, `ScaStageRunner Closures`, `Vulnerability Store Tests`, `Git Cloner Tests`, `IngestionException`, `AuditResultModelsTest.php`, `StoreAuditTest`, `Stage Status Enum`, `.token`, `Dependency Store Tests`, `SastStageRunnerTest.php`, `Show Audit Tests`, `OSV Query & Live Test`, `Result Model Tests`, `App Config Tests`, `BuildsScaResults`, `AuditScan Model Tests`, `AuditVulnerabilityFixMatchTest`, `SastStageRunnerTest`, `MetricsRunnerTest`, `SemgrepRunnerTest`, `ApiErrorFormatTest.php`, `EslintRunnerTest`?**
  _High betweenness centrality (0.155) - this node is a cross-community bridge._
- **What connects `DetailUnavailable`, `NoRangeData`, `NotInListedRanges` to the rest of the system?**
  _110 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `API Feature Tests` be split into smaller, more focused modules?**
  _Cohesion score 0.07686274509803921 - nodes in this community are weakly interconnected._
- **Why does `AuditScan` connect `AuditScan` to `.dependency`, `API Feature Tests`, `ScanFinding`, `User`, `Vulnerability`, `ScaStageRunner Tests`, `AuditScan Model`, `ProcessAuditJobTest.php`, `Code Language & Metric Scope`, `Dependency Relation Enums`, `Audit Status Enum`, `AuditStage`, `FindingTool`, `ProcessAuditJob & Store Tests`, `Audit Status Manager`, `.guestAudit`, `SourceType`, `ScaStageRunner Closures`, `Vulnerability Store Tests`, `Git Cloner Tests`, `AuditResultModelsTest.php`, `StoreAuditTest`, `Stage Status Enum`, `.token`, `AuditController.php`, `MetricScope`, `Illuminate\Support\Carbon`, `Dependency Store Tests`, `AuditDependencyController.php`, `StoreAuditRequest.php`, `SastStageRunnerTest.php`, `BuildsScaResults`, `AuditVulnerabilityFixMatchTest`, `SastStageRunnerTest`, `AuditWorkspace`, `AuditVulnerabilityController.php`, `MetricsRunnerTest`, `.row`, `SastStageRunner`?**
  _High betweenness centrality (0.132) - this node is a cross-community bridge._
- **Should `RepositoryFileWalker` be split into smaller, more focused modules?**
  _Cohesion score 0.06253652834599649 - nodes in this community are weakly interconnected._

### Low-confidence Hints
_AMBIGUOUS edges — the extractor was unsure. Verify before acting on these._

- **What is the exact relationship between `robots.txt` and `Backend Laravel README`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._
- **What is the exact relationship between `Backend Laravel README` and `Project README`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._