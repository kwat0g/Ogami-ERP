<?php

declare(strict_types=1);

namespace App\Modules\Quality\Models;

use App\Common\Traits\HasHashId;
use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionDefectSummaryPhoto extends Model
{
    use HasHashId;

    protected $fillable = [
        'inspection_defect_summary_id', 'file_path', 'file_name', 'mime_type', 'file_size', 'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    protected $hidden = ['file_path'];

    public function summary(): BelongsTo
    {
        return $this->belongsTo(InspectionDefectSummary::class, 'inspection_defect_summary_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
