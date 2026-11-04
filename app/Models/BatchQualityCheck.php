<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One measured or inspected quality check on a production batch. */
class BatchQualityCheck extends Model
{
    public const TYPES = [
        'ph' => 'pH',
        'brix' => 'Brix',
        'temperature' => 'Processing temperature',
        'seal' => 'Seal / packaging integrity',
        'label' => 'Label and date-code check',
        'micro' => 'Microbiological test',
        'sensory' => 'Taste / appearance',
        'other' => 'Other',
    ];

    public const RESULTS = ['pass' => 'Pass', 'fail' => 'Fail'];

    protected $fillable = ['production_id', 'check_type', 'measured_value', 'unit', 'spec_min', 'spec_max', 'result', 'lab_report_ref', 'checked_by', 'checked_at', 'notes'];

    protected $casts = [
        'measured_value' => 'decimal:4',
        'spec_min' => 'decimal:4',
        'spec_max' => 'decimal:4',
        'checked_at' => 'datetime',
    ];

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    /** A measured value outside its stated limits, whatever result was recorded. */
    public function isOutOfSpec(): bool
    {
        if ($this->measured_value === null) {
            return false;
        }
        $value = (float) $this->measured_value;

        return ($this->spec_min !== null && $value < (float) $this->spec_min)
            || ($this->spec_max !== null && $value > (float) $this->spec_max);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->check_type] ?? $this->check_type;
    }
}
