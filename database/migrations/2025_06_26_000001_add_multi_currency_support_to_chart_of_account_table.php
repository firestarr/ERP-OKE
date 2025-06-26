<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMultiCurrencySupportToChartOfAccountTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ChartOfAccount', function (Blueprint $table) {
            // Add multi-currency support fields
            $table->string('default_currency', 3)->default('USD')->after('parent_account_id')
                  ->comment('Default currency for this account (ISO 4217 code)');
            
            $table->boolean('allow_multi_currency')->default(false)->after('default_currency')
                  ->comment('Whether this account allows transactions in multiple currencies');
            
            // Add foreign key constraint to system_currencies
            $table->foreign('default_currency')->references('code')->on('system_currencies')
                  ->onUpdate('cascade')->onDelete('restrict');
            
            // Add indexes for performance
            $table->index('default_currency');
            $table->index('allow_multi_currency');
            $table->index(['account_type', 'default_currency']);
        });
        
        // Update JournalEntryLine table to support multi-currency
        Schema::table('JournalEntryLine', function (Blueprint $table) {
            // Check if currency fields don't already exist
            if (!Schema::hasColumn('JournalEntryLine', 'currency')) {
                $table->string('currency', 3)->nullable()->after('description')
                      ->comment('Currency code for this journal entry line');
                
                $table->decimal('foreign_amount', 15, 4)->nullable()->after('currency')
                      ->comment('Amount in foreign currency');
                
                $table->decimal('exchange_rate', 15, 6)->nullable()->after('foreign_amount')
                      ->comment('Exchange rate used for conversion');
                
                $table->string('base_currency', 3)->default('USD')->after('exchange_rate')
                      ->comment('Base currency for conversion');
                
                // Add foreign key constraints
                $table->foreign('currency')->references('code')->on('system_currencies')
                      ->onUpdate('cascade')->onDelete('restrict');
                
                $table->foreign('base_currency')->references('code')->on('system_currencies')
                      ->onUpdate('cascade')->onDelete('restrict');
                
                // Add indexes
                $table->index('currency');
                $table->index(['account_id', 'currency']);
                $table->index(['journal_id', 'currency']);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('JournalEntryLine', function (Blueprint $table) {
            // Drop foreign key constraints first
            if (Schema::hasColumn('JournalEntryLine', 'currency')) {
                $table->dropForeign(['currency']);
                $table->dropForeign(['base_currency']);
                
                // Drop indexes
                $table->dropIndex(['journal_entry_line_currency_index']);
                $table->dropIndex(['journal_entry_line_account_id_currency_index']);
                $table->dropIndex(['journal_entry_line_journal_id_currency_index']);
                
                // Drop columns
                $table->dropColumn([
                    'currency',
                    'foreign_amount', 
                    'exchange_rate',
                    'base_currency'
                ]);
            }
        });
        
        Schema::table('ChartOfAccount', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['default_currency']);
            
            // Drop indexes
            $table->dropIndex(['chart_of_account_default_currency_index']);
            $table->dropIndex(['chart_of_account_allow_multi_currency_index']);
            $table->dropIndex(['chart_of_account_account_type_default_currency_index']);
            
            // Drop columns
            $table->dropColumn([
                'default_currency',
                'allow_multi_currency'
            ]);
        });
    }
}