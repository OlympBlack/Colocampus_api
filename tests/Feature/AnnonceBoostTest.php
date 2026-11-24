<?php

namespace Tests\Feature;

use App\Models\Annonce;
use App\Models\User;
use App\Services\KkiapayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnonceBoostTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_boost_packs(): void
    {
        $response = $this->getJson('/api/boost-packs');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'titre', 'description', 'prix', 'duree_jours', 'badge']
                ]
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals(200, $data[0]['prix']);
        $this->assertEquals(3, $data[0]['duree_jours']);
        $this->assertEquals(500, $data[1]['prix']);
        $this->assertEquals(7, $data[1]['duree_jours']);
    }

    public function test_user_can_boost_own_annonce_and_it_ranks_first(): void
    {
        $user = User::factory()->create([
            'prenoms' => 'Jean',
            'telephone' => '+22997000000',
            'role' => 'demarcheur',
            'is_verified' => true,
            'statut_verification' => 'valide',
        ]);

        $annonce1 = Annonce::create([
            'user_id' => $user->id,
            'titre' => 'Chambre 1 Non Boostée',
            'quartier' => 'Calavi',
            'loyer' => 20000,
            'caution' => 20000,
            'source_eau' => 'SONEB',
            'type_electricite' => 'SBEE_CARTE',
            'type_annonce' => 'CHAMBRE_VIDE',
            'statut_disponibilite' => 'available',
            'statut_publication' => 'active',
            'created_at' => now()->subDay(),
        ]);

        $annonce2 = Annonce::create([
            'user_id' => $user->id,
            'titre' => 'Chambre 2 A Booster',
            'quartier' => 'Calavi',
            'loyer' => 25000,
            'caution' => 25000,
            'source_eau' => 'SONEB',
            'type_electricite' => 'SBEE_CARTE',
            'type_annonce' => 'CHAMBRE_VIDE',
            'statut_disponibilite' => 'available',
            'statut_publication' => 'active',
            'created_at' => now()->subDays(2),
        ]);

        // Mock Kkiapay
        $mockKkiapay = \Mockery::mock(KkiapayService::class);
        $mockKkiapay->shouldReceive('verifyTransaction')
            ->with('tx_boost_test_123')
            ->andReturn([
                'status' => 'SUCCESS',
                'amount' => 200,
                'transactionId' => 'tx_boost_test_123',
                'currency' => 'XOF',
            ]);
        $this->app->instance(KkiapayService::class, $mockKkiapay);

        $response = $this->actingAs($user)
            ->postJson("/api/annonces/{$annonce2->id}/boost", [
                'pack_type' => 'boost_3j',
                'transaction_id' => 'tx_boost_test_123',
                'methode' => 'kkiapay',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $fresh = $annonce2->fresh();
        $this->assertTrue((bool) $fresh->est_boostee);
        $this->assertNotNull($fresh->boostee_jusqu_a);
        $this->assertTrue($fresh->boostee_jusqu_a->isFuture());

        // Check listing: boosted annonce2 MUST be returned before older/newer non-boosted annonce1
        $listResponse = $this->getJson('/api/annonces');
        $listResponse->assertStatus(200);
        $items = $listResponse->json('data');

        $this->assertEquals($annonce2->id, $items[0]['id']);
        $this->assertTrue($items[0]['est_boostee']);
    }
}
