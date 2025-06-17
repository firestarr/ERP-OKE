<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestForQuotation extends Model
{
    use HasFactory;

    protected $table = 'request_for_quotations';
    protected $primaryKey = 'rfq_id';
    protected $fillable = [
        'rfq_number',
        'rfq_date',
        'validity_date',
        'status',
        'notes',
        'reference_document'
    ];

    protected $casts = [
        'rfq_date' => 'date',
        'validity_date' => 'date',
    ];

    public function lines()
    {
        return $this->hasMany(RFQLine::class, 'rfq_id');
    }

    public function vendorQuotations()
    {
        return $this->hasMany(VendorQuotation::class, 'rfq_id');
    }

    /**
     * Relasi dengan vendors yang dipilih saat konversi
     * Menggunakan tabel pivot rfq_vendors
     */
    public function selectedVendors()
    {
        return $this->belongsToMany(Vendor::class, 'rfq_vendors', 'rfq_id', 'vendor_id')
                    ->withPivot('status', 'selected_at', 'sent_at')
                    ->withTimestamps();
    }

    /**
     * Relasi dengan RFQVendor model untuk akses yang lebih mudah
     */
    public function rfqVendors()
    {
        return $this->hasMany(RFQVendor::class, 'rfq_id');
    }

    /**
     * Get vendors that are selected but not sent yet
     */
    public function getSelectedVendorsAttribute()
    {
        return $this->selectedVendors()->wherePivot('status', 'selected')->get();
    }

    /**
     * Get vendors that have been sent RFQ
     */
    public function getSentVendorsAttribute()
    {
        return $this->selectedVendors()->wherePivot('status', 'sent')->get();
    }

    /**
     * Check if RFQ has any selected vendors
     */
    public function hasSelectedVendors()
    {
        return $this->selectedVendors()->count() > 0;
    }

    /**
     * Add vendor to RFQ
     */
    public function addVendor($vendorId, $status = 'selected')
    {
        return $this->selectedVendors()->attach($vendorId, [
            'status' => $status,
            'selected_at' => now()
        ]);
    }

    /**
     * Remove vendor from RFQ
     */
    public function removeVendor($vendorId)
    {
        return $this->selectedVendors()->detach($vendorId);
    }

    /**
     * Mark vendors as sent
     */
    public function markVendorsAsSent($vendorIds = null)
    {
        $query = $this->rfqVendors();
        
        if ($vendorIds) {
            $query->whereIn('vendor_id', $vendorIds);
        }
        
        return $query->update([
            'status' => 'sent',
            'sent_at' => now()
        ]);
    }
}