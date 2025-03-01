<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trend extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'niche',
        'keywords',
        'metadata',
        'popularity_score',
        'source',
        'fetched_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'keywords' => 'array',
        'metadata' => 'array',
        'fetched_at' => 'datetime',
    ];

    /**
     * Get the contents associated with the trend.
     */
    public function contents()
    {
        return $this->hasMany(Content::class);
    }

    /**
     * Scope a query to only include trends from a specific niche.
     */
    public function scopeByNiche($query, $niche)
    {
        return $query->where('niche', $niche);
    }

    /**
     * Scope a query to order trends by popularity.
     */
    public function scopePopular($query)
    {
        return $query->orderBy('popularity_score', 'desc');
    }

    /**
     * Scope a query to get recent trends.
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('fetched_at', 'desc');
    }
}
