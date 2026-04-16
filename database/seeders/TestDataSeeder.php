<?php

namespace Database\Seeders;

use App\Enums\ApprovalStatusEnum;
use App\Enums\DosageFormEnum;
use App\Models\ApiClient;
use App\Models\Manufacturer;
use App\Models\Medicine;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $manufacturer = Manufacturer::create([
            'name' => 'Sun Pharmaceutical Industries Ltd.',
            'slug' => 'sun-pharma',
            'country_code' => 'IN',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'is_active' => true,
        ]);

        $medicine = Medicine::create([
            'name' => 'Admenta 10 Tablet',
            'slug' => 'admenta-10-tablet',
            'short_composition' => 'Memantine (10mg)',
            'description' => 'Used in the treatment of Alzheimer\'s disease.',
            'dosage_form' => DosageFormEnum::Tablet,
            'manufacturer_id' => $manufacturer->id,
            'price' => 150.00,
            'mrp' => 175.00,
            'currency' => 'INR',
            'approval_status' => ApprovalStatusEnum::Published,
            'published_at' => now(),
            'rx_required' => true,
            'rx_required_header' => 'Rx',
        ]);

        $apiClient = ApiClient::create([
            'name' => 'Test API Client',
            'owner_email' => 'test@example.com',
            'environment' => 'sandbox',
            'abilities' => ['medicines:read', 'medicines:search'],
            'is_active' => true,
        ]);

        $token = $apiClient->createToken('test_token', ['medicines:read', 'medicines:search']);

        $this->command->info('Test Data Seeded!');
        $this->command->info('API Token: '.$token->plainTextToken);
    }
}
