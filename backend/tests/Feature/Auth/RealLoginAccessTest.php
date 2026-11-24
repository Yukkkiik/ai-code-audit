<?php

namespace Tests\Feature\Auth;

use App\Models\AuditScan;
use App\Models\ScanFinding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsScaResults;
use Tests\Support\LogsInViaGoogle;
use Tests\TestCase;

/**
 * Matriks akses endpoint audit dengan session hasil login OAuth sungguhan (A-33h(3), A-36g).
 */
class RealLoginAccessTest extends TestCase
{
    use BuildsScaResults, LogsInViaGoogle, RefreshDatabase;

    private User $owner;

    private AuditScan $audit;

    private int $vulnerabilityId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->audit = AuditScan::factory()->ownedBy($this->owner)->create($this->finished());
        ScanFinding::factory()->create(['audit_scan_id' => $this->audit->id]);
        $this->vulnerabilityId = $this->vulnerability($this->dependency($this->audit))->id;
    }

    /**
     * @return list<string>
     */
    private function auditUrls(): array
    {
        $base = "/api/v1/audits/{$this->audit->public_id}";

        return [
            $base,
            "$base/findings",
            "$base/metrics",
            "$base/dependencies",
            "$base/vulnerabilities",
            "$base/vulnerabilities/{$this->vulnerabilityId}",
        ];
    }

    public function test_owner_session_reads_every_audit_endpoint(): void
    {
        $cookie = $this->loginViaCallback($this->owner);

        foreach ($this->auditUrls() as $url) {
            $this->withSessionCookie($cookie)->getJson($url)->assertOk();
        }

        $this->withSessionCookie($cookie)->getJson('/api/v1/audits')
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->audit->public_id);
    }

    public function test_another_users_session_gets_not_found_on_every_audit_endpoint(): void
    {
        $cookie = $this->loginViaCallback(User::factory()->create());

        foreach ($this->auditUrls() as $url) {
            $this->withSessionCookie($cookie)->getJson($url)->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        }

        $this->withSessionCookie($cookie)->getJson('/api/v1/audits')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_request_without_session_gets_not_found_or_unauthenticated(): void
    {
        foreach ($this->auditUrls() as $url) {
            $this->withoutSessionCookie()->getJson($url)->assertNotFound();
        }

        $this->withoutSessionCookie()->getJson('/api/v1/audits')->assertUnauthorized();
    }

    public function test_guest_token_works_alongside_a_logged_in_session_of_another_user(): void
    {
        [$guestAudit, $token] = $this->guestAudit();
        $cookie = $this->loginViaCallback($this->owner);
        $url = "/api/v1/audits/{$guestAudit->public_id}";

        $this->withSessionCookie($cookie)->getJson($url)->assertNotFound();
        $this->withSessionCookie($cookie)->getJson($url, $this->token($token))->assertOk();
    }

    public function test_old_session_cookie_loses_access_after_logout(): void
    {
        $cookie = $this->loginViaCallback($this->owner);
        $this->withSessionCookie($cookie)->getJson($this->auditUrls()[0])->assertOk();

        $this->withSessionCookie($cookie)->postJson('/api/v1/auth/logout')->assertNoContent();

        foreach ($this->auditUrls() as $url) {
            $this->withSessionCookie($cookie)->getJson($url)->assertNotFound();
        }
        $this->withSessionCookie($cookie)->getJson('/api/v1/audits')->assertUnauthorized();
    }
}
