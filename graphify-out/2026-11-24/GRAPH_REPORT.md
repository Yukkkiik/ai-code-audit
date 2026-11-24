# Graph Report - ai-code-security-audit-platform  (2026-11-24)

## Corpus Check
- 309 files · ~107,092 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 10 file(s) not represented in the graph (top: (none) 7, .xml 1, .ico 1)

## Summary
- 2773 nodes · 6985 edges · 150 communities (86 shown, 64 thin omitted)
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
- ScaStageRunner
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
- UnresolvedReason
- GoogleOAuthTest
- SastListCollection
- Illuminate\Http\Request
- FixedVersionMatcherTest.php
- .parse
- Git Cloner Tests
- Manifest JSON Reader
- HealthCategory
- SafeZipExtractorTest
- ExplicitVersionsMatchTest.php
- SastStageRunnerTest
- StoreAuditTest
- SAST & AI Schema Concepts
- StageStatus
- .token
- SastScanSummary
- .index
- OsvClient
- AuditVulnerabilityCombinedFixTest
- Dependency Store Tests
- Architecture & Auth Concepts
- AuditVulnerabilityExplicitVersionsTest.php
- StoreAuditTest
- AuditDependencyController.php
- IngestionException
- GitUrlValidator
- SastStageRunnerTest.php
- Show Audit Tests
- FileMetrics
- API Contract & Roadmap
- SafeZipExtractorTest.php
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
- GoogleUserResolver
- SastStageRunnerTest
- AuditWorkspace
- AuditVulnerabilityController.php
- FindingNormalizerTest
- MetricsRunnerTest
- rules
- SemgrepRunnerTest
- .store
- GitUrlValidatorTest
- AppServiceProvider.php
- scripts
- .row
- ScaTablesTest
- ManifestFailure
- EslintRunnerTest
- EnvExampleTest
- QueueProbeJob
- SastStageRunner
- VulnerabilityDetailResource
- MetricScope
- RepositoryIngestor.php
- DatabaseConnectionTest
- DatabaseSeeder.php
- require-dev
- sanctum.php
- SourceType
- config
- ListAuditsTest
- AuditController
- .callback
- Closure
- ToolReport
- require
- ListAuditsRequest
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

## Communities (150 total, 64 thin omitted)

### Community 0 - ".dependency"
Cohesion: 0.25
Nodes (3): AuditVulnerabilitiesTest, {closure#1}(), {closure#2}()

### Community 1 - "API Feature Tests"
Cohesion: 0.08
Nodes (7): HealthCheckTest, AuditScansTableTest, DatabaseSchemaTest, ExampleTest, QueueConnectionTest, InteractsWithSchema, TestCase

### Community 2 - "RepositoryFileWalker"
Cohesion: 0.07
Nodes (8): {closure#1}(), RepositoryFileWalker, JsTsFileSelector, JsTsSelection, {closure#1}(), RepositoryFileWalkerTest, JsTsFileSelectorTest, ManifestFinderTest

### Community 3 - "Users Table & DB Tests"
Cohesion: 0.09
Nodes (3): ManifestProject, UsersTableTest, DependencyCollectorTest

### Community 5 - "Fix Match Reasons"
Cohesion: 0.13
Nodes (7): FixMatchReason, DetailUnavailable, InvalidVersion, NoRangeData, NotInListedRanges, UnevaluableRange, FixedVersionMatcherTest

### Community 6 - "composer.json"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 10 - "Database Migrations"
Cohesion: 0.06
Nodes (12): {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}() (+4 more)

### Community 11 - "ScaStageRunner"
Cohesion: 0.22
Nodes (3): {closure#2}(), DependencyStore, ScaStageRunner

### Community 12 - "Vulnerability"
Cohesion: 0.07
Nodes (11): AiSource, Llm, StaticTemplate, AiRefactoring, AiUsageLog, CodeMetric, Dependency, ScanFinding (+3 more)

### Community 14 - "AuditScan Model"
Cohesion: 0.10
Nodes (4): {closure#1}(), {closure#2}(), User, AuditScanFactory

### Community 16 - "OSV Client Test Closures"
Cohesion: 0.08
Nodes (17): OsvVulnerabilityRef, {closure#1}(), {closure#11}(), {closure#15}(), {closure#17}(), {closure#18}(), {closure#2}(), {closure#20}() (+9 more)

### Community 17 - "Code Language & Metric Scope"
Cohesion: 0.08
Nodes (11): AiStatus, Completed, Failed, Pending, RejectedValidation, AiRefactoringFactory, AiUsageLogFactory, CodeMetricFactory (+3 more)

### Community 18 - "DependencyScope"
Cohesion: 0.08
Nodes (14): DependencyRelation, Direct, Transitive, DependencyScope, Development, Optional, Peer, Production (+6 more)

### Community 19 - "FixedVersionMatcher"
Cohesion: 0.11
Nodes (12): FixMatchStatus, FixAvailable, NoFixRecorded, Undetermined, VersionChange, Major, Minor, Patch (+4 more)

### Community 20 - "Audit Status Enum"
Cohesion: 0.09
Nodes (20): AuditStatus, Completed, Failed, Ingesting, Partial, Queued, Scanning, InvalidAuditTransitionException (+12 more)

### Community 21 - "PHPUnit\Framework\TestCase"
Cohesion: 0.08
Nodes (5): ExampleTest, {closure#1}(), {closure#4}(), {closure#4}(), SemverVersionTest

### Community 22 - "AuditStage"
Cohesion: 0.12
Nodes (8): AuditStage, Sast, Sca, AuditStageRunners, StageRunner, UnavailableStageRunner, IngestedRepository, RecordingStageRunner

### Community 23 - "FindingTool"
Cohesion: 0.10
Nodes (17): FindingCategory, BestPractice, Quality, Security, Severity, Critical, High, Info (+9 more)

### Community 25 - "ProcessAuditJob & Store Tests"
Cohesion: 0.15
Nodes (3): ProcessAuditJob, {closure#1}(), ProcessAuditJobTest

### Community 26 - "metrics.mjs"
Cohesion: 0.08
Nodes (27): analyze(), CLOSING_KINDS, DECISION_KINDS, decisions(), FUNCTION_KINDS, functionName(), halstead(), IGNORED_KINDS (+19 more)

### Community 29 - ".guestAudit"
Cohesion: 0.24
Nodes (3): AuditFindingsTest, {closure#1}(), {closure#3}()

### Community 30 - "Manifest Type & Lock Parser"
Cohesion: 0.09
Nodes (7): ManifestType, PackageJson, PackageLockV2, PackageLockV3, ExactVersion, PackageLockParser, ParsedLockfile

### Community 31 - "OsvFailureReason"
Cohesion: 0.10
Nodes (11): OsvFailureReason, ClientError, InvalidResponse, PaginationIncomplete, RateLimited, ServerError, TimeBudgetExhausted, Timeout (+3 more)

### Community 34 - "DependencyCollectorTest.php"
Cohesion: 0.15
Nodes (3): CollectedDependencies, DependencyCollector, ManifestFinder

### Community 35 - "Frontend Build Packages"
Cohesion: 0.13
Nodes (20): devDependencies, concurrently@^10.0.3, laravel-vite-plugin@^3.1, tailwindcss@^4.0.0, @tailwindcss/vite@^4.0.0, vite@^8.0.0, optionalDependencies, @laravel/multiplex@^0.4.1 (+12 more)

### Community 36 - "ScaStageRunner Closures"
Cohesion: 0.10
Nodes (20): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+12 more)

### Community 37 - "UnresolvedReason"
Cohesion: 0.13
Nodes (10): UnresolvedReason, LockfileUnreadable, MissingVersion, NonRegistrySource, RangeWithoutLockfile, UnsupportedLockfile, {closure#1}(), {closure#2}() (+2 more)

### Community 39 - "SastListCollection"
Cohesion: 0.15
Nodes (5): AuditCollection, MetricCollection, SastListCollection, ScaListCollection, VulnerabilityCollection

### Community 40 - "Illuminate\Http\Request"
Cohesion: 0.12
Nodes (8): AuditResource, DependencyResource, FindingResource, MetricResource, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}()

### Community 41 - "FixedVersionMatcherTest.php"
Cohesion: 0.10
Nodes (6): {closure#2}(), {closure#3}(), SemverVersion, {closure#3}(), {closure#4}(), {closure#8}()

### Community 42 - ".parse"
Cohesion: 0.05
Nodes (12): VulnerabilitySeverity, Critical, High, Low, Medium, Unknown, OsvVulnerabilityDetail, OsvVulnerabilityParser (+4 more)

### Community 43 - "Git Cloner Tests"
Cohesion: 0.18
Nodes (3): {closure#2}(), {closure#3}(), GitRepositoryClonerTest

### Community 44 - "Manifest JSON Reader"
Cohesion: 0.15
Nodes (3): ManifestParseException, ManifestJsonReader, ManifestJsonReaderTest

### Community 45 - "HealthCategory"
Cohesion: 0.13
Nodes (8): HealthCategory, Fair, Good, Poor, HealthScore, HealthScoreCalculator, MetricsAggregate, HealthScoreCalculatorTest

### Community 47 - "ExplicitVersionsMatchTest.php"
Cohesion: 0.11
Nodes (4): AdvisoryAffects, {closure#10}(), {closure#11}(), {closure#7}()

### Community 49 - "StoreAuditTest"
Cohesion: 0.05
Nodes (8): HostResolver, ingest(), AuditWorkspaceTest, RepositoryIngestorTest, BuildsZipArchives, FakeGitBinary, FakeHostResolver, UsesTemporaryAuditStorage

### Community 50 - "SAST & AI Schema Concepts"
Cohesion: 0.21
Nodes (17): Metrics: Cyclomatic Complexity, Halstead, MI, sast-ast-rules skill, Finding normalization, Health score (no SCA), Severity mapping per tool, ai_refactorings table, ai_usage_logs table (atomic daily quota), audit_scans table (+9 more)

### Community 51 - "StageStatus"
Cohesion: 0.10
Nodes (17): FindingTool, Eslint, Semgrep, StageStatus, Completed, Failed, Partial, Pending (+9 more)

### Community 52 - ".token"
Cohesion: 0.18
Nodes (4): AuditDependenciesTest, {closure#1}(), {closure#2}(), BuildsScaResults

### Community 53 - "SastScanSummary"
Cohesion: 0.13
Nodes (7): AuditFindingController, AuditMetricController, Controller, FindingCollection, AuditAccessGuard, {closure#1}(), SastScanSummary

### Community 54 - ".index"
Cohesion: 0.18
Nodes (4): ListAuditChildrenRequest, ListFindingsRequest, ListMetricsRequest, ListVulnerabilitiesRequest

### Community 55 - "OsvClient"
Cohesion: 0.11
Nodes (7): OsvRequestException, {closure#2}(), {closure#3}(), {closure#5}(), {closure#6}(), {closure#7}(), OsvClient

### Community 57 - "Dependency Store Tests"
Cohesion: 0.15
Nodes (8): OsvQueryStatus, Failed, NotQueried, Queried, {closure#1}(), {closure#2}(), {closure#4}(), DependencyStoreTest

### Community 58 - "Architecture & Auth Concepts"
Cohesion: 0.14
Nodes (14): Authentication and audit access rules, Architecture, Guest Token (X-Guest-Token, hashed), Backend Module Breakdown (Services/*), ProcessAuditJob orchestrator, StageRunner interface, Tech Stack (Next.js, Laravel, MySQL, Redis, Semgrep, ESLint, OSV.dev, LLM), Backend Laravel README (+6 more)

### Community 59 - "AuditVulnerabilityExplicitVersionsTest.php"
Cohesion: 0.08
Nodes (6): {closure#1}(), {closure#3}(), VulnerabilityStore, {closure#1}(), AuditVulnerabilityExplicitVersionsTest, {closure#2}()

### Community 61 - "AuditDependencyController.php"
Cohesion: 0.15
Nodes (5): AuditDependencyController, ListDependenciesRequest, DependencyCollection, ScaCoverage, ScaScanSummary

### Community 64 - "SastStageRunnerTest.php"
Cohesion: 0.10
Nodes (5): {closure#1}(), {closure#1}(), SastIgnoredDirectoriesTest, {closure#1}(), {closure#1}()

### Community 66 - "FileMetrics"
Cohesion: 0.15
Nodes (6): CodeLanguage, Javascript, Typescript, FileMetrics, MetricsParser, MetricsReport

### Community 67 - "API Contract & Roadmap"
Cohesion: 0.20
Nodes (11): Audit endpoints (POST/GET /audits), API Contract, Rate limit policy, AI Remediation endpoints, Report endpoints (JSON/PDF), Uniform error response format, Implementation Roadmap, TAHAP 6 SAST Engine (not started) (+3 more)

### Community 68 - "SafeZipExtractorTest.php"
Cohesion: 0.21
Nodes (9): {closure#10}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+1 more)

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
Cohesion: 0.14
Nodes (4): EslintParser, {closure#2}(), SastText, EslintParserTest

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
Cohesion: 0.11
Nodes (4): SastToolException, SemgrepParser, {closure#2}(), SemgrepParserTest

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
Cohesion: 0.16
Nodes (5): MetricsResult, ToolRunResult, {closure#1}(), SastResultStoreTest, run()

### Community 111 - "AuditWorkspace"
Cohesion: 0.17
Nodes (3): {closure#1}(), AuditWorkspace, RepositoryIngestor

### Community 115 - "rules"
Cohesion: 0.13
Nodes (15): category, cwe, owasp, pattern, pattern, pattern-either, pattern-not, rules (+7 more)

### Community 119 - "AppServiceProvider.php"
Cohesion: 0.09
Nodes (8): AppServiceProvider, {closure#2}(), {closure#3}(), {closure#5}(), DnsHostResolver, FindingNormalizer, NormalizedFindings, SnippetReader

### Community 120 - "scripts"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 121 - ".row"
Cohesion: 0.21
Nodes (4): FunctionMetrics, MetricValues, {closure#2}(), MetricsTest

### Community 127 - "SastStageRunner"
Cohesion: 0.17
Nodes (6): EslintRunner, MetricsRunner, SastResultStore, SastStageRunner, SastToolExecutor, SemgrepRunner

### Community 130 - "MetricScope"
Cohesion: 0.20
Nodes (4): MetricScope, File, Function, {closure#2}()

### Community 135 - "require-dev"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pail, laravel/pao, laravel/pint, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 137 - "SourceType"
Cohesion: 0.38
Nodes (4): SourceType, Git, Zip, {closure#1}()

### Community 138 - "config"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

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
- **110 isolated node(s):** `Critical`, `High`, `Low`, `Medium`, `Unknown` (+105 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 715 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **64 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `TestCase` connect `API Feature Tests` to `.dependency`, `MetricScope`, `Users Table & DB Tests`, `DatabaseConnectionTest`, `AiTablesTest`, `OSV Client Tests`, `ListAuditsTest`, `Vulnerability`, `ScaStageRunner Tests`, `RedisConnectionTest`, `OSV Client Test Closures`, `Audit Status Enum`, `ProcessAuditJob & Store Tests`, `Audit Status Manager`, `OSV Details Tests`, `.guestAudit`, `SourceType`, `Web Routes & API Errors`, `ScaStageRunner Closures`, `.parse`, `Git Cloner Tests`, `SafeZipExtractorTest`, `SastStageRunnerTest`, `StoreAuditTest`, `StageStatus`, `.token`, `Dependency Store Tests`, `StoreAuditTest`, `SastStageRunnerTest.php`, `Show Audit Tests`, `SafeZipExtractorTest.php`, `OSV Query & Live Test`, `Result Model Tests`, `App Config Tests`, `BuildsScaResults`, `AuditVulnerabilityFixMatchTest`, `SastStageRunnerTest`, `MetricsRunnerTest`, `SemgrepRunnerTest`, `EslintRunnerTest`?**
  _High betweenness centrality (0.176) - this node is a cross-community bridge._
- **What connects `Critical`, `High`, `Low` to the rest of the system?**
  _110 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `API Feature Tests` be split into smaller, more focused modules?**
  _Cohesion score 0.07993966817496229 - nodes in this community are weakly interconnected._
- **Why does `AuditScan` connect `Audit Status Enum` to `.dependency`, `API Feature Tests`, `MetricScope`, `RepositoryIngestor.php`, `ScaStageRunner`, `AuditController`, `Vulnerability`, `AuditScan Model`, `ScaStageRunner Tests`, `Code Language & Metric Scope`, `DependencyScope`, `AuditStage`, `ProcessAuditJob & Store Tests`, `Audit Status Manager`, `.guestAudit`, `SourceType`, `ScaStageRunner Closures`, `Illuminate\Http\Request`, `.parse`, `SastStageRunnerTest`, `StoreAuditTest`, `StageStatus`, `.token`, `SastScanSummary`, `.index`, `Dependency Store Tests`, `AuditDependencyController.php`, `SastStageRunnerTest.php`, `BuildsScaResults`, `AuditVulnerabilityFixMatchTest`, `SastStageRunnerTest`, `AuditWorkspace`, `AuditVulnerabilityController.php`, `MetricsRunnerTest`, `.row`, `SastStageRunner`?**
  _High betweenness centrality (0.134) - this node is a cross-community bridge._
- **Should `RepositoryFileWalker` be split into smaller, more focused modules?**
  _Cohesion score 0.07088989441930618 - nodes in this community are weakly interconnected._

### Low-confidence Hints
_AMBIGUOUS edges — the extractor was unsure. Verify before acting on these._

- **What is the exact relationship between `robots.txt` and `Backend Laravel README`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._
- **What is the exact relationship between `Backend Laravel README` and `Project README`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._