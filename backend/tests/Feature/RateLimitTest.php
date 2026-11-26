<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_se_bloquea_tras_cinco_intentos_fallidos(): void
    {
        $user = User::factory()->create();
        $bad = ['email' => $user->email, 'password' => 'incorrecta'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', $bad)->assertStatus(422);
        }

        $this->postJson('/api/login', $bad)
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_el_bloqueo_de_un_email_no_afecta_a_otro(): void
    {
        $victim = User::factory()->create();
        $other = User::factory()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/login', ['email' => $victim->email, 'password' => 'x']);
        }

        $this->postJson('/api/login', ['email' => $other->email, 'password' => 'password'])
            ->assertOk();
    }

    public function test_una_ip_no_puede_probar_mas_de_20_emails_por_minuto(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/login', ['email' => "u{$i}@demo.test", 'password' => 'x'])->assertStatus(422);
        }

        $this->postJson('/api/login', ['email' => 'otro@demo.test', 'password' => 'x'])->assertStatus(429);
    }

    public function test_la_api_autenticada_limita_a_60_peticiones_por_minuto(): void
    {
        Sanctum::actingAs(User::factory()->create());

        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/me')->assertOk();
        }

        $this->getJson('/api/me')->assertStatus(429);
    }
}
