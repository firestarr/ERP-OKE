<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SystemCurrenciesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        DB::table('system_currencies')->truncate();
        
        $currencies = $this->getCurrencyData();
        
        foreach ($currencies as $currency) {
            $currency['created_at'] = now();
            $currency['updated_at'] = now();
            
            DB::table('system_currencies')->insert($currency);
        }
        
        $this->command->info('✅ System currencies seeded successfully!');
        $this->command->info('📊 Total currencies: ' . count($currencies));
        $this->command->info('💰 Base currency: USD');
    }
    
    /**
     * Get comprehensive currency data
     */
    private function getCurrencyData(): array
    {
        return [
            // 🇺🇸 US Dollar - Base Currency
            [
                'code' => 'USD',
                'name' => 'US Dollar',
                'symbol' => '$',
                'decimal_places' => 2,
                'is_active' => true,
                'is_base_currency' => true,
                'sort_order' => 1,
                'countries' => json_encode([
                    'United States', 'Ecuador', 'El Salvador', 'Marshall Islands',
                    'Micronesia', 'Palau', 'Timor-Leste', 'British Virgin Islands',
                    'Caribbean Netherlands', 'Turks and Caicos Islands'
                ])
            ],
            
            // 🇮🇩 Indonesian Rupiah
            [
                'code' => 'IDR',
                'name' => 'Indonesian Rupiah',
                'symbol' => 'Rp',
                'decimal_places' => 0, // No decimal for IDR
                'is_active' => true,
                'is_base_currency' => false,
                'sort_order' => 2,
                'countries' => json_encode(['Indonesia'])
            ],
            
            // 🇪🇺 Euro
            [
                'code' => 'EUR',
                'name' => 'Euro',
                'symbol' => '€',
                'decimal_places' => 2,
                'is_active' => true,
                'is_base_currency' => false,
                'sort_order' => 3,
                'countries' => json_encode([
                    'Germany', 'France', 'Italy', 'Spain', 'Netherlands',
                    'Belgium', 'Austria', 'Portugal', 'Finland', 'Greece',
                    'Ireland', 'Luxembourg', 'Slovenia', 'Cyprus', 'Malta',
                    'Slovakia', 'Estonia', 'Latvia', 'Lithuania'
                ])
            ],
            
            // 🇸🇬 Singapore Dollar
            [
                'code' => 'SGD',
                'name' => 'Singapore Dollar',
                'symbol' => 'S$',
                'decimal_places' => 2,
                'is_active' => true,
                'is_base_currency' => false,
                'sort_order' => 4,
                'countries' => json_encode(['Singapore'])
            ],
            
            // 🇯🇵 Japanese Yen
            [
                'code' => 'JPY',
                'name' => 'Japanese Yen',
                'symbol' => '¥',
                'decimal_places' => 0, // No decimal for JPY
                'is_active' => true,
                'is_base_currency' => false,
                'sort_order' => 5,
                'countries' => json_encode(['Japan'])
            ],
            
            // 🇨🇳 Chinese Yuan
            [
                'code' => 'CNY',
                'name' => 'Chinese Yuan Renminbi',
                'symbol' => '¥',
                'decimal_places' => 2,
                'is_active' => true,
                'is_base_currency' => false,
                'sort_order' => 6,
                'countries' => json_encode(['China'])
            ],
            
            // 🇬🇧 British Pound
            [
                'code' => 'GBP',
                'name' => 'British Pound Sterling',
                'symbol' => '£',
                'decimal_places' => 2,
                'is_active' => true,
                'is_base_currency' => false,
                'sort_order' => 7,
                'countries' => json_encode([
                    'United Kingdom', 'Isle of Man', 'Jersey', 'Guernsey',
                    'South Georgia and the South Sandwich Islands'
                ])
            ],
            
            // 🇦🇺 Australian Dollar
            [
                'code' => 'AUD',
                'name' => 'Australian Dollar',
                'symbol' => 'A$',
                'decimal_places' => 2,
                'is_active' => true,
                'is_base_currency' => false,
                'sort_order' => 8,
                'countries' => json_encode([
                    'Australia', 'Christmas Island', 'Cocos Islands',
                    'Norfolk Island', 'Kiribati', 'Nauru', 'Tuvalu'
                ])
            ],
            
            // 🇨🇦 Canadian Dollar
            [
                'code' => 'CAD',
                'name' => 'Canadian Dollar',
                'symbol' => 'C$',
                'decimal_places' => 2,
                'is_active' => true,
                'is_base_currency' => false,
                'sort_order' => 9,
                'countries' => json_encode(['Canada'])
            ],
            
            // 🇨🇭 Swiss Franc
            [
                'code' => 'CHF',
                'name' => 'Swiss Franc',
                'symbol' => 'CHF',
                'decimal_places' => 2,
                'is_active' => true,
                'is_base_currency' => false,
                'sort_order' => 10,
                'countries' => json_encode(['Switzerland', 'Liechtenstein'])
            ],
            
            // 🇲🇾 Malaysian Ringgit
            [
                'code' => 'MYR',
                'name' => 'Malaysian Ringgit',
                'symbol' => 'RM',
                'decimal_places' => 2,
                'is_active' => true,
                'is_base_currency' => false,
                'sort_order' => 11,
                'countries' => json_encode(['Malaysia'])
            ],
            
            // 🇹🇭 Thai Baht
            [
                'code' => 'THB',
                'name' => 'Thai Baht',
                'symbol' => '฿',
                'decimal_places' => 2,
                'is_active' => true,
                'is_base_currency' => false,
                'sort_order' => 12,
                'countries' => json_encode(['Thailand'])
            ],
            
            // 🇵🇭 Philippine Peso
            [
                'code' => 'PHP',
                'name' => 'Philippine Peso',
                'symbol' => '₱',
                'decimal_places' => 2,
                'is_active' => true,
                'is_base_currency' => false,
                'sort_order' => 13,
                'countries' => json_encode(['Philippines'])
            ],
            
            // 🇻🇳 Vietnamese Dong
            [
                'code' => 'VND',
                'name' => 'Vietnamese Dong',
                'symbol' => '₫',
                'decimal_places' => 0, // No decimal for VND
                'is_active' => true,
                'is_base_currency' => false,
                'sort_order' => 14,
                'countries' => json_encode(['Vietnam'])
            ],
            
            // 🇰🇷 South Korean Won
            [
                'code' => 'KRW',
                'name' => 'South Korean Won',
                'symbol' => '₩',
                'decimal_places' => 0, // No decimal for KRW
                'is_active' => true,
                'is_base_currency' => false,
                'sort_order' => 15,
                'countries' => json_encode(['South Korea'])
            ],
            
            // 🇮🇳 Indian Rupee
            [
                'code' => 'INR',
                'name' => 'Indian Rupee',
                'symbol' => '₹',
                'decimal_places' => 2,
                'is_active' => true,
                'is_base_currency' => false,
                'sort_order' => 16,
                'countries' => json_encode(['India'])
            ],
            
            // 🇭🇰 Hong Kong Dollar
            [
                'code' => 'HKD',
                'name' => 'Hong Kong Dollar',
                'symbol' => 'HK$',
                'decimal_places' => 2,
                'is_active' => true,
                'is_base_currency' => false,
                'sort_order' => 17,
                'countries' => json_encode(['Hong Kong'])
            ],
            
            // 🇹🇼 Taiwan Dollar
            [
                'code' => 'TWD',
                'name' => 'Taiwan New Dollar',
                'symbol' => 'NT$',
                'decimal_places' => 2,
                'is_active' => true,
                'is_base_currency' => false,
                'sort_order' => 18,
                'countries' => json_encode(['Taiwan'])
            ],
            
            // 🇳🇿 New Zealand Dollar
            [
                'code' => 'NZD',
                'name' => 'New Zealand Dollar',
                'symbol' => 'NZ$',
                'decimal_places' => 2,
                'is_active' => true,
                'is_base_currency' => false,
                'sort_order' => 19,
                'countries' => json_encode(['New Zealand', 'Cook Islands', 'Niue', 'Pitcairn Islands', 'Tokelau'])
            ],
            
            // 🇸🇪 Swedish Krona
            [
                'code' => 'SEK',
                'name' => 'Swedish Krona',
                'symbol' => 'kr',
                'decimal_places' => 2,
                'is_active' => false, // Less common in Asian business
                'is_base_currency' => false,
                'sort_order' => 20,
                'countries' => json_encode(['Sweden'])
            ],
            
            // 🇳🇴 Norwegian Krone
            [
                'code' => 'NOK',
                'name' => 'Norwegian Krone',
                'symbol' => 'kr',
                'decimal_places' => 2,
                'is_active' => false, // Less common in Asian business
                'is_base_currency' => false,
                'sort_order' => 21,
                'countries' => json_encode(['Norway', 'Svalbard', 'Jan Mayen', 'Bouvet Island'])
            ],
            
            // 🇩🇰 Danish Krone
            [
                'code' => 'DKK',
                'name' => 'Danish Krone',
                'symbol' => 'kr',
                'decimal_places' => 2,
                'is_active' => false, // Less common in Asian business
                'is_base_currency' => false,
                'sort_order' => 22,
                'countries' => json_encode(['Denmark', 'Faroe Islands', 'Greenland'])
            ],
            
            // 🇧🇷 Brazilian Real
            [
                'code' => 'BRL',
                'name' => 'Brazilian Real',
                'symbol' => 'R$',
                'decimal_places' => 2,
                'is_active' => false, // Less common, can be activated if needed
                'is_base_currency' => false,
                'sort_order' => 23,
                'countries' => json_encode(['Brazil'])
            ],
            
            // 🇷🇺 Russian Ruble
            [
                'code' => 'RUB',
                'name' => 'Russian Ruble',
                'symbol' => '₽',
                'decimal_places' => 2,
                'is_active' => false, // Can be activated if needed
                'is_base_currency' => false,
                'sort_order' => 24,
                'countries' => json_encode(['Russia'])
            ],
            
            // 🇿🇦 South African Rand
            [
                'code' => 'ZAR',
                'name' => 'South African Rand',
                'symbol' => 'R',
                'decimal_places' => 2,
                'is_active' => false, // Can be activated if needed
                'is_base_currency' => false,
                'sort_order' => 25,
                'countries' => json_encode(['South Africa', 'Lesotho', 'Namibia', 'Eswatini'])
            ]
        ];
    }
}