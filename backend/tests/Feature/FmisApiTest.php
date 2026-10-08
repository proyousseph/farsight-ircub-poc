<?php

namespace Tests\Feature;

use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\SeedsIrcubBasics;
use Tests\TestCase;

class FmisApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsIrcubBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBasics();
        $this->ensureDemoPayer();
    }

    public function test_create_post_reverse_and_recon_flow(): void
    {
        Sanctum::actingAs($this->supervisorUser());

        Payment::query()->create([
            'payer_id' => $this->ensureDemoPayer()->id,
            'revenue_code' => 'BIZLIC',
            'amount' => 55.5,
            'currency' => 'USD',
            'channel' => 'CASH',
            'external_ref' => 'FMIS-AUTO-'.uniqid(),
            'paid_at' => now(),
            'status' => 'SUCCESS',
            'fmis_status' => 'PENDING',
            'created_by' => $this->supervisorUser()->id,
        ]);

        $date = now()->toDateString();

        $created = $this->postJson('/api/fmis/batches', [
            'journal_date' => $date,
            'post_immediately' => false,
        ])->assertCreated();

        $batchId = $created->json('batch.id');
        $this->assertNotEmpty($batchId);
        $this->assertSame('PENDING', $created->json('batch.status'));

        $this->postJson("/api/fmis/batches/{$batchId}/post")
            ->assertOk()
            ->assertJsonPath('batch.status', 'POSTED');

        // Double-post blocked
        $this->postJson("/api/fmis/batches/{$batchId}/post")->assertStatus(422);

        $recon = $this->getJson('/api/fmis/reconciliation?date='.$date)->assertOk();
        $this->assertEqualsWithDelta(0, (float) $recon->json('summary.difference'), 0.01);

        $this->postJson("/api/fmis/batches/{$batchId}/reverse")
            ->assertOk()
            ->assertJsonPath('batch.status', 'REVERSED');

        $lines = \App\Models\FmisJournalLine::query()
            ->where('fmis_journal_batch_id', $batchId)
            ->get();
        $this->assertNotEmpty($lines);
        foreach ($lines as $line) {
            $this->assertNotNull($line->payment_id);
            $this->assertSame((int) $line->payment_id, (int) $line->source_payment_id);
            $this->assertNotNull($line->reversed_at);
        }
    }

    public function test_gl_mappings_list(): void
    {
        Sanctum::actingAs($this->supervisorUser());

        $this->getJson('/api/gl-mappings')
            ->assertOk();
    }
}
