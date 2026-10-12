<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A floor plan (optionally from an uploaded PDF) with generated and hand-placed devices. */
class SiteLayout extends Model
{
    /** Service layers a layout can include. key => label. */
    public const LAYERS = [
        'network' => 'Network',
        'cabinet' => 'Data Cabinets',
        'cabling' => 'Data Cabling',
        'paging' => 'Paging System',
        'cctv' => 'CCTV Installation',
        'wifi' => 'WiFi Setup',
        'termination' => 'End-to-End Termination',
        'pbx' => 'IP PBX Installation',
    ];

    protected $fillable = ['user_id', 'name', 'plan_path', 'plan_name', 'width_m', 'layers', 'settings', 'devices'];

    protected function casts(): array
    {
        return [
            'width_m' => 'float',
            'layers' => 'array',
            'settings' => 'array',
            'devices' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
