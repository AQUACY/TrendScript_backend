<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Content extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'contents';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'trend_id',
        'title',
        'description',
        'script_structure',
        'seo_data',
        'status',
        'archived_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'script_structure' => 'array',
        'seo_data' => 'array',
        'archived_at' => 'datetime',
    ];

    /**
     * Get the user that owns the content.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the trend associated with the content.
     */
    public function trend()
    {
        return $this->belongsTo(Trend::class);
    }

    /**
     * Scope a query to only include active content.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include archived content.
     */
    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }

    /**
     * Archive the content.
     */
    public function archive()
    {
        $this->status = 'archived';
        $this->archived_at = now();
        $this->save();
    }

    /**
     * Restore the content.
     */
    public function restore()
    {
        $this->status = 'active';
        $this->archived_at = null;
        $this->save();
    }
}
