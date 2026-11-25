# Graph Report - ai-code-security-audit-platform  (2026-11-24)

## Corpus Check
- 313 files · ~108,163 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 10 file(s) not represented in the graph (top: (none) 7, .xml 1, .ico 1)

## Summary
- 2808 nodes · 7079 edges · 149 communities (86 shown, 63 thin omitted)
- Extraction: 97% EXTRACTED · 3% INFERRED · 0% AMBIGUOUS · INFERRED: 221 edges (avg confidence: 0.84)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `3171ad59`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- .dependency
- API Feature Tests
- RepositoryFileWalker
- Users Table & DB Tests
- ExplicitVersionsMatchTest
- Fix Match Reasons
- composer.json
- AiTablesTest
- Package Lock Parser Tests
- OSV Client Tests
- Database Migrations
- DependencyStore.php
- Vulnerability
- ScaStageRunner Tests
- AuditScan Model
- RedisConnectionTest
- OSV Client Test Closures
- Code Language & Metric Scope
- DependencyScope
- FixedVersionMatcher
- Audit Status Enum
- PHPUnit\Framework\TestCase
- ProcessAuditJobTest.php
- FindingTool
- Package Json Parser Tests
- ProcessAuditJob & Store Tests
- metrics.mjs
- Audit Status Manager
- OSV Details Tests
- .guestAudit
- Manifest Type & Lock Parser
- OsvFailureReason
- AuditController.php
- Web Routes & API Errors
- DependencyCollectorTest.php
- Frontend Build Packages
- ScaStageRunner Closures
- PackageJsonParser
- GoogleOAuthTest
- SastListCollection
- Illuminate\Http\Request
- FixedVersionMatcherTest.php
- OsvVulnerabilityParserTest
- Git Cloner Tests
- Manifest JSON Reader
- HealthCategory
- SafeZipExtractorTest
- ExplicitVersionsMatchTest.php
- VulnerabilityStoreTest
- SAST & AI Schema Concepts
- StageStatus
- .token
- AuditScan
- AiRefactoringFactory.php
- OsvClient
- AuditVulnerabilityCombinedFixTest
- Dependency Store Tests
- Architecture & Auth Concepts
- AuditVulnerabilityExplicitVersionsTest.php
- StoreAuditTest
- OsvVulnerabilityDetail
- IngestionException
- GitUrlValidator
- SastStageRunnerTest.php
- Show Audit Tests
- SastToolException
- API Contract & Roadmap
- SafeZipExtractorTest.php
- CLAUDE.md Working Rules
- OSV Query & Live Test
- Result Model Tests
- AI Guardrail & State Machine
- SCA Audit Concepts
- SCA Endpoints & Roadmap Items
- Illuminate\Support\Facades\Log
- EslintParser
- App Config Tests
- Lockfile v2 Fixture
- BuildsScaResults
- Repo Sandbox Safety
- SemgrepParser
- api.php
- Testing Strategy
- AuditVulnerabilityFixMatchTest
- CombinedFixMatchTest
- Fixture body-parser
- Fixture fsevents
- Fixture jest
- Fixture jest-util
- Fixture ms
- Decisions & Requirements
- SafeZipExtractor
- Fixture express
- Fixture qs
- Fixture scoped pkg
- Fixture nested qs
- Systematic Debugging
- AI Remediation Reporting
- ApiErrorFormatTest.php
- ToolRunStatus
- AuditWorkspace
- AuditVulnerabilityController.php
- FindingNormalizerTest
- MetricsRunnerTest
- rules
- SemgrepRunnerTest
- SourceType
- GitUrlValidatorTest
- AppServiceProvider.php
- scripts
- .row
- AuditVulnerabilityExplicitVersionsTest
- ManifestFailure
- .parse
- EnvExampleTest
- QueueProbeJob
- ManifestFinderTest
- JsTsFileSelectorTest
- VulnerabilityDetailResource
- MetricScope
- RepositoryIngestor.php
- DatabaseConnectionTest
- DatabaseSeeder.php
- bootstrap/app.php
- require-dev
- ManifestFinder
- AuditWorkspaceTest
- config
- ListAuditsTest
- VulnerabilityMatch
- .callback
- FakeHostResolver
- AuditScanModelTest
- require
- JsTsSelection
- psr-4
- autoload-dev
- extra

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

## Communities (149 total, 63 thin omitted)

### Community 0 - ".dependency"
Cohesion: 0.25
Nodes (3): AuditVulnerabilitiesTest, {closure#1}(), {closure#2}()

### Community 1 - "API Feature Tests"
Cohesion: 0.07
Nodes (10): HealthCheckTest, {closure#1}(), AuditScansTableTest, DatabaseSchemaTest, ExampleTest, QueueConnectionTest, SastIgnoredDirectoriesTest, InteractsWithSchema (+2 more)

### Community 2 - "RepositoryFileWalker"
Cohesion: 0.20
Nodes (4): {closure#1}(), RepositoryFileWalker, JsTsFileSelector, RepositoryFileWalkerTest

### Community 5 - "Fix Match Reasons"
Cohesion: 0.13
Nodes (7): FixMatchReason, DetailUnavailable, InvalidVersion, NoRangeData, NotInListedRanges, UnevaluableRange, FixedVersionMatcherTest

### Community 6 - "composer.json"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 9 - "OSV Client Tests"
Cohesion: 0.11
Nodes (8): {closure#15}(), {closure#17}(), {closure#20}(), {closure#22}(), {closure#23}(), {closure#4}(), {closure#7}(), OsvClientTest

### Community 10 - "Database Migrations"
Cohesion: 0.06
Nodes (12): {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}() (+4 more)

### Community 12 - "Vulnerability"
Cohesion: 0.09
Nodes (9): Ecosystem, Npm, AiRefactoring, AiUsageLog, CodeMetric, Dependency, ScanFinding, ScanToolRun (+1 more)

### Community 14 - "AuditScan Model"
Cohesion: 0.10
Nodes (4): {closure#1}(), {closure#2}(), User, AuditScanFactory

### Community 16 - "OSV Client Test Closures"
Cohesion: 0.10
Nodes (8): {closure#11}(), {closure#18}(), {closure#2}(), {closure#24}(), {closure#3}(), {closure#5}(), {closure#8}(), {closure#9}()

### Community 17 - "Code Language & Metric Scope"
Cohesion: 0.12
Nodes (7): AiUsageLogFactory, CodeMetricFactory, DependencyFactory, ScanFindingFactory, ScanToolRunFactory, UserFactory, VulnerabilityFactory

### Community 18 - "DependencyScope"
Cohesion: 0.08
Nodes (14): DependencyRelation, Direct, Transitive, DependencyScope, Development, Optional, Peer, Production (+6 more)

### Community 19 - "FixedVersionMatcher"
Cohesion: 0.09
Nodes (13): FixMatchStatus, FixAvailable, NoFixRecorded, Undetermined, VersionChange, Major, Minor, Patch (+5 more)

### Community 20 - "Audit Status Enum"
Cohesion: 0.13
Nodes (15): AuditStatus, Completed, Failed, Ingesting, Partial, Queued, Scanning, InvalidAuditTransitionException (+7 more)

### Community 22 - "ProcessAuditJobTest.php"
Cohesion: 0.07
Nodes (13): AuditStage, Sast, Sca, AuditStageRunners, StageResult, StageRunner, UnavailableStageRunner, IngestedRepository (+5 more)

### Community 23 - "FindingTool"
Cohesion: 0.08
Nodes (19): FindingCategory, BestPractice, Quality, Security, Severity, Critical, High, Info (+11 more)

### Community 25 - "ProcessAuditJob & Store Tests"
Cohesion: 0.16
Nodes (3): ProcessAuditJob, {closure#1}(), ProcessAuditJobTest

### Community 26 - "metrics.mjs"
Cohesion: 0.08
Nodes (27): analyze(), CLOSING_KINDS, DECISION_KINDS, decisions(), FUNCTION_KINDS, functionName(), halstead(), IGNORED_KINDS (+19 more)

### Community 29 - ".guestAudit"
Cohesion: 0.19
Nodes (6): FindingTool, Eslint, Semgrep, AuditFindingsTest, {closure#1}(), {closure#3}()

### Community 30 - "Manifest Type & Lock Parser"
Cohesion: 0.09
Nodes (7): ManifestType, PackageJson, PackageLockV2, PackageLockV3, ExactVersion, PackageLockParser, ParsedLockfile

### Community 31 - "OsvFailureReason"
Cohesion: 0.10
Nodes (11): OsvFailureReason, ClientError, InvalidResponse, PaginationIncomplete, RateLimited, ServerError, TimeBudgetExhausted, Timeout (+3 more)

### Community 32 - "AuditController.php"
Cohesion: 0.09
Nodes (7): ApiExceptionRenderer, AuditController, {closure#1}(), AuditResource, AuditScanCreator, CreatedAuditScan, AuditScanCreatorTest

### Community 35 - "Frontend Build Packages"
Cohesion: 0.13
Nodes (20): devDependencies, concurrently@^10.0.3, laravel-vite-plugin@^3.1, tailwindcss@^4.0.0, @tailwindcss/vite@^4.0.0, vite@^8.0.0, optionalDependencies, @laravel/multiplex@^0.4.1 (+12 more)

### Community 36 - "ScaStageRunner Closures"
Cohesion: 0.10
Nodes (20): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+12 more)

### Community 38 - "GoogleOAuthTest"
Cohesion: 0.05
Nodes (7): GoogleUserResolver, GoogleOAuthTest, GoogleUserResolverTest, RealLoginAccessTest, SessionLogoutTest, ScaTablesTest, LogsInViaGoogle

### Community 39 - "SastListCollection"
Cohesion: 0.18
Nodes (4): AuditCollection, SastListCollection, ScaListCollection, VulnerabilityCollection

### Community 40 - "Illuminate\Http\Request"
Cohesion: 0.19
Nodes (5): NoStoreResponse, DependencyResource, FindingResource, MetricResource, UserResource

### Community 41 - "FixedVersionMatcherTest.php"
Cohesion: 0.09
Nodes (6): {closure#2}(), {closure#3}(), SemverVersion, {closure#3}(), {closure#4}(), {closure#8}()

### Community 42 - "OsvVulnerabilityParserTest"
Cohesion: 0.12
Nodes (7): VulnerabilitySeverity, Critical, High, Low, Medium, Unknown, OsvVulnerabilityParserTest

### Community 43 - "Git Cloner Tests"
Cohesion: 0.14
Nodes (5): {closure#2}(), {closure#3}(), GitRepositoryClonerTest, RepositoryIngestorTest, FakeGitBinary

### Community 44 - "Manifest JSON Reader"
Cohesion: 0.18
Nodes (3): ManifestParseException, ManifestJsonReader, ManifestJsonReaderTest

### Community 45 - "HealthCategory"
Cohesion: 0.12
Nodes (8): HealthCategory, Fair, Good, Poor, HealthScore, HealthScoreCalculator, MetricsAggregate, HealthScoreCalculatorTest

### Community 47 - "ExplicitVersionsMatchTest.php"
Cohesion: 0.15
Nodes (3): {closure#10}(), {closure#11}(), {closure#7}()

### Community 50 - "SAST & AI Schema Concepts"
Cohesion: 0.21
Nodes (17): Metrics: Cyclomatic Complexity, Halstead, MI, sast-ast-rules skill, Finding normalization, Health score (no SCA), Severity mapping per tool, ai_refactorings table, ai_usage_logs table (atomic daily quota), audit_scans table (+9 more)

### Community 51 - "StageStatus"
Cohesion: 0.20
Nodes (8): StageStatus, Completed, Failed, Partial, Pending, Running, Skipped, SastScanSummaryTest

### Community 53 - "AuditScan"
Cohesion: 0.06
Nodes (19): AuditDependencyController, AuditFindingController, AuditMetricController, Controller, ListAuditChildrenRequest, ListDependenciesRequest, ListFindingsRequest, ListMetricsRequest (+11 more)

### Community 54 - "AiRefactoringFactory.php"
Cohesion: 0.12
Nodes (9): AiSource, Llm, StaticTemplate, AiStatus, Completed, Failed, Pending, RejectedValidation (+1 more)

### Community 55 - "OsvClient"
Cohesion: 0.11
Nodes (7): OsvRequestException, {closure#2}(), {closure#3}(), {closure#5}(), {closure#6}(), {closure#7}(), OsvClient

### Community 57 - "Dependency Store Tests"
Cohesion: 0.13
Nodes (10): UnresolvedReason, LockfileUnreadable, MissingVersion, NonRegistrySource, RangeWithoutLockfile, UnsupportedLockfile, {closure#1}(), {closure#2}() (+2 more)

### Community 58 - "Architecture & Auth Concepts"
Cohesion: 0.14
Nodes (14): Authentication and audit access rules, Architecture, Guest Token (X-Guest-Token, hashed), Backend Module Breakdown (Services/*), ProcessAuditJob orchestrator, StageRunner interface, Tech Stack (Next.js, Laravel, MySQL, Redis, Semgrep, ESLint, OSV.dev, LLM), Backend Laravel README (+6 more)

### Community 59 - "AuditVulnerabilityExplicitVersionsTest.php"
Cohesion: 0.08
Nodes (8): {closure#1}(), {closure#3}(), VulnerabilityStore, {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), BuildsScaResults

### Community 63 - "GitUrlValidator"
Cohesion: 0.16
Nodes (3): InvalidRepositoryUrlException, GitUrlValidator, HostResolver

### Community 64 - "SastStageRunnerTest.php"
Cohesion: 0.10
Nodes (5): SastToolExecutor, {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}()

### Community 66 - "SastToolException"
Cohesion: 0.10
Nodes (6): SastToolException, MetricsParser, MetricsReport, {closure#2}(), MetricsRunner, ToolReport

### Community 67 - "API Contract & Roadmap"
Cohesion: 0.20
Nodes (11): Audit endpoints (POST/GET /audits), API Contract, Rate limit policy, AI Remediation endpoints, Report endpoints (JSON/PDF), Uniform error response format, Implementation Roadmap, TAHAP 6 SAST Engine (not started) (+3 more)

### Community 68 - "SafeZipExtractorTest.php"
Cohesion: 0.23
Nodes (8): {closure#10}(), {closure#2}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}(), {closure#9}()

### Community 69 - "CLAUDE.md Working Rules"
Cohesion: 0.20
Nodes (11): Anti-AI-Slop rules, CLAUDE.md Working Rules, Git rules, Plan-First workflow (FASE A/B), brainstorming skill, Requirements exploration, test-driven-development skill, Red-Green-Refactor cycle (+3 more)

### Community 70 - "OSV Query & Live Test"
Cohesion: 0.22
Nodes (3): OsvQuery, {closure#1}(), OsvLiveTest

### Community 71 - "Result Model Tests"
Cohesion: 0.12
Nodes (5): OsvQueryStatus, Failed, NotQueried, Queried, AuditResultModelsTest

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
Cohesion: 0.09
Nodes (6): EslintParser, {closure#2}(), EslintRunner, SastText, EslintRunnerTest, EslintParserTest

### Community 78 - "Lockfile v2 Fixture"
Cohesion: 0.22
Nodes (8): dependencies, legacy-only, resolved, version, lockfileVersion, name, requires, version

### Community 79 - "BuildsScaResults"
Cohesion: 0.27
Nodes (3): AuditMetricsTest, {closure#1}(), {closure#2}()

### Community 80 - "Repo Sandbox Safety"
Cohesion: 0.38
Nodes (7): Architecture Security Principles, Non-negotiable security rules, repo-sandbox-safety skill, Safe git clone via Symfony Process, Git URL SSRF protection, ZIP Slip prevention (manual entry extraction), Ingestion edge cases

### Community 81 - "SemgrepParser"
Cohesion: 0.16
Nodes (4): SemgrepParser, {closure#2}(), SemgrepRunner, SemgrepParserTest

### Community 83 - "Testing Strategy"
Cohesion: 0.33
Nodes (6): requesting-code-review skill, Self-review checklist, Definition of Done checklist, Testing Strategy, SCA test scenarios, Testing database and Redis safeguards

### Community 86 - "CombinedFixMatchTest"
Cohesion: 0.15
Nodes (3): {closure#1}(), {closure#4}(), CombinedFixMatchTest

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

### Community 109 - "ApiErrorFormatTest.php"
Cohesion: 0.12
Nodes (3): AuditScanPolicy, GET /(), POST /validation()

### Community 110 - "ToolRunStatus"
Cohesion: 0.07
Nodes (12): ToolRunStatus, Completed, Failed, Skipped, MetricsResult, SastResultStore, SastStageRunner, ToolRunResult (+4 more)

### Community 115 - "rules"
Cohesion: 0.13
Nodes (15): category, cwe, owasp, pattern, pattern, pattern-either, pattern-not, rules (+7 more)

### Community 117 - "SourceType"
Cohesion: 0.11
Nodes (8): SourceType, Git, Zip, ListAuditsRequest, {closure#1}(), {closure#2}(), StoreAuditRequest, ValidatedGitUrl

### Community 119 - "AppServiceProvider.php"
Cohesion: 0.12
Nodes (6): AppServiceProvider, {closure#2}(), {closure#3}(), {closure#5}(), DnsHostResolver, SnippetReader

### Community 120 - "scripts"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 121 - ".row"
Cohesion: 0.17
Nodes (8): CodeLanguage, Javascript, Typescript, FileMetrics, FunctionMetrics, MetricValues, {closure#2}(), MetricsTest

### Community 130 - "MetricScope"
Cohesion: 0.25
Nodes (3): MetricScope, File, Function

### Community 134 - "bootstrap/app.php"
Cohesion: 0.22
Nodes (4): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}()

### Community 135 - "require-dev"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pail, laravel/pao, laravel/pint, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 136 - "ManifestFinder"
Cohesion: 0.20
Nodes (3): {closure#1}(), ManifestFinder, ManifestProject

### Community 138 - "config"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 140 - "VulnerabilityMatch"
Cohesion: 0.32
Nodes (3): OsvVulnerabilityRef, VulnerabilityMatch, {closure#1}()

### Community 144 - "require"
Cohesion: 0.33
Nodes (6): require, laravel/framework, laravel/sanctum, laravel/socialite, laravel/tinker, php

### Community 146 - "psr-4"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 148 - "autoload-dev"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 149 - "extra"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

## Ambiguous Edges - Review These
- `robots.txt` → `Backend Laravel README`  [AMBIGUOUS]
  backend/public/robots.txt · relation: conceptually_related_to
- `Backend Laravel README` → `Project README`  [AMBIGUOUS]
  backend/README.md · relation: conceptually_related_to

## Knowledge Gaps
- **110 isolated node(s):** `Llm`, `StaticTemplate`, `File`, `Function`, `Git` (+105 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 715 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **63 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `TestCase` connect `API Feature Tests` to `.dependency`, `MetricScope`, `Users Table & DB Tests`, `DatabaseConnectionTest`, `AiTablesTest`, `AuditWorkspaceTest`, `OSV Client Tests`, `ListAuditsTest`, `Vulnerability`, `ScaStageRunner Tests`, `AuditScanModelTest`, `RedisConnectionTest`, `OSV Client Test Closures`, `ProcessAuditJobTest.php`, `ProcessAuditJob & Store Tests`, `Audit Status Manager`, `OSV Details Tests`, `.guestAudit`, `AuditController.php`, `Web Routes & API Errors`, `ScaStageRunner Closures`, `Git Cloner Tests`, `SafeZipExtractorTest`, `VulnerabilityStoreTest`, `StageStatus`, `.token`, `Dependency Store Tests`, `AuditVulnerabilityExplicitVersionsTest.php`, `StoreAuditTest`, `SastStageRunnerTest.php`, `Show Audit Tests`, `SafeZipExtractorTest.php`, `OSV Query & Live Test`, `Result Model Tests`, `EslintParser`, `App Config Tests`, `BuildsScaResults`, `AuditVulnerabilityFixMatchTest`, `ApiErrorFormatTest.php`, `ToolRunStatus`, `MetricsRunnerTest`, `SemgrepRunnerTest`, `QueueProbeJob`?**
  _High betweenness centrality (0.149) - this node is a cross-community bridge._
- **What connects `Llm`, `StaticTemplate`, `File` to the rest of the system?**
  _110 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `API Feature Tests` be split into smaller, more focused modules?**
  _Cohesion score 0.06896551724137931 - nodes in this community are weakly interconnected._
- **Why does `AuditScan` connect `AuditScan` to `.dependency`, `API Feature Tests`, `MetricScope`, `RepositoryIngestor.php`, `DependencyStore.php`, `Vulnerability`, `ScaStageRunner Tests`, `AuditScan Model`, `Code Language & Metric Scope`, `DependencyScope`, `Audit Status Enum`, `ProcessAuditJobTest.php`, `ProcessAuditJob & Store Tests`, `Audit Status Manager`, `.guestAudit`, `AuditController.php`, `ScaStageRunner Closures`, `Git Cloner Tests`, `VulnerabilityStoreTest`, `StageStatus`, `.token`, `Dependency Store Tests`, `AuditVulnerabilityExplicitVersionsTest.php`, `SastStageRunnerTest.php`, `Illuminate\Support\Facades\Log`, `BuildsScaResults`, `AuditVulnerabilityFixMatchTest`, `ApiErrorFormatTest.php`, `ToolRunStatus`, `AuditWorkspace`, `AuditVulnerabilityController.php`, `MetricsRunnerTest`, `SourceType`, `.row`?**
  _High betweenness centrality (0.119) - this node is a cross-community bridge._
- **Should `Users Table & DB Tests` be split into smaller, more focused modules?**
  _Cohesion score 0.09182692307692308 - nodes in this community are weakly interconnected._

### Low-confidence Hints
_AMBIGUOUS edges — the extractor was unsure. Verify before acting on these._

- **What is the exact relationship between `robots.txt` and `Backend Laravel README`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._
- **What is the exact relationship between `Backend Laravel README` and `Project README`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._