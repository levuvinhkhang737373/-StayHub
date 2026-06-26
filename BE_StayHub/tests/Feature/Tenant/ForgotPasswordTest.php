<?php

namespace Tests\Feature\Tenant;

use App\Models\Admin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\TenantForgotPasswordOtpMail;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Admin
        $this->superAdmin = Admin::create([
            'username' => 'superadmin_test',
            'full_name' => 'Super Admin Test',
            'email' => 'superadmin_test@stayhub.local',
            'phone' => '0901234567',
            'password' => bcrypt('password'),
            'role' => Admin::ROLE_SUPER_ADMIN,
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'address' => 'Test Address',
        ]);

        // Create Tenant
        $this->tenant = Tenant::create([
            'username' => 'tenant_test',
            'full_name' => 'Tenant Test Name',
            'email' => 'tenant@stayhub.local',
            'phone' => '0911111111',
            'password' => bcrypt('old_password'),
            'role' => 1,
            'status' => Tenant::STATUS_RENTING,
            'gender' => Tenant::GENDER_MALE,
            'identity_type' => Tenant::IDENTITY_TYPE_CCCD,
            'identity_number' => '123456789012',
            'date_of_birth' => '2000-01-01',
            'created_by' => $this->superAdmin->id,
        ]);
    }

    public function test_tenant_can_request_forgot_password_otp()
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/tenant/forgot-password', [
            'email' => 'tenant@stayhub.local',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Mã OTP đặt lại mật khẩu đã được gửi đến email của bạn.');

        // Assert record created in db
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'tenant@stayhub.local',
        ]);

        // Retrieve the generated OTP
        $otpRecord = DB::table('password_reset_tokens')
            ->where('email', 'tenant@stayhub.local')
            ->first();

        $this->assertNotNull($otpRecord);
        $this->assertEquals(6, strlen($otpRecord->token));

        Mail::assertQueued(TenantForgotPasswordOtpMail::class, function ($mail) use ($otpRecord) {
            return $mail->hasTo('tenant@stayhub.local') && $mail->otp === $otpRecord->token;
        });
    }

    public function test_forgot_password_requires_existing_active_email()
    {
        $response = $this->postJson('/api/v1/tenant/forgot-password', [
            'email' => 'nonexistent@stayhub.local',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', false);
    }

    public function test_tenant_can_reset_password_with_valid_otp()
    {
        // 1. Setup the OTP token in database
        DB::table('password_reset_tokens')->insert([
            'email' => 'tenant@stayhub.local',
            'token' => '123456',
            'created_at' => now(),
        ]);

        // 2. Submit the reset password request
        $response = $this->postJson('/api/v1/tenant/reset-password', [
            'email' => 'tenant@stayhub.local',
            'otp' => '123456',
            'password' => 'new_password_123',
            'password_confirmation' => 'new_password_123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Đặt lại mật khẩu thành công. Vui lòng đăng nhập lại.');

        // 3. Verify database updates
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'tenant@stayhub.local',
        ]);

        $this->tenant->refresh();
        $this->assertTrue(Hash::check('new_password_123', $this->tenant->password));
    }

    public function test_reset_password_fails_with_invalid_otp()
    {
        DB::table('password_reset_tokens')->insert([
            'email' => 'tenant@stayhub.local',
            'token' => '123456',
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/tenant/reset-password', [
            'email' => 'tenant@stayhub.local',
            'otp' => '000000', // incorrect OTP
            'password' => 'new_password_123',
            'password_confirmation' => 'new_password_123',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Mã OTP không chính xác.');
    }

    public function test_reset_password_fails_with_expired_otp()
    {
        DB::table('password_reset_tokens')->insert([
            'email' => 'tenant@stayhub.local',
            'token' => '123456',
            'created_at' => now()->subMinutes(16), // expired (15m limit)
        ]);

        $response = $this->postJson('/api/v1/tenant/reset-password', [
            'email' => 'tenant@stayhub.local',
            'otp' => '123456',
            'password' => 'new_password_123',
            'password_confirmation' => 'new_password_123',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Mã OTP đã hết hạn. Vui lòng yêu cầu mã mới.');
    }
}
