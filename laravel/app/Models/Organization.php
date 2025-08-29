<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'domain',
        'database_name',
        'api_key',
        'settings',
        'is_active',
        'collection_name'
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean'
    ];

    public function organizationData()
    {
        return $this->hasMany(OrganizationData::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function dataSources()
    {
        return $this->hasMany(DataSource::class);
    }

    public function getCollectionNameAttribute($value)
    {
        // If collection_name is set in database, use it
        if ($value) {
            return $value;
        }
        
        // Fallback to old format for backward compatibility
        return "org_{$this->id}_data";
    }

    /**
     * Generate a collection name from organization name
     */
    public static function generateCollectionName(string $organizationName): string
    {
        // Convert to lowercase, replace spaces and special chars with underscores
        $collectionName = strtolower($organizationName);
        $collectionName = preg_replace('/[^a-z0-9]+/', '_', $collectionName);
        $collectionName = trim($collectionName, '_');
        
        // Ensure it starts with a letter (Qdrant requirement)
        if (!preg_match('/^[a-z]/', $collectionName)) {
            $collectionName = 'org_' . $collectionName;
        }
        
        // Ensure it's not too long (Qdrant has limits)
        if (strlen($collectionName) > 50) {
            $collectionName = substr($collectionName, 0, 50);
        }
        
        return $collectionName;
    }

    /**
     * Validate collection name format
     */
    public static function validateCollectionName(string $collectionName): bool
    {
        // Must start with letter, contain only lowercase letters, numbers, underscores
        // Must be between 1-50 characters
        return preg_match('/^[a-z][a-z0-9_]{0,49}$/', $collectionName);
    }

    /**
     * Check if a collection name is available
     */
    public static function isCollectionNameAvailable(string $collectionName, ?int $excludeId = null): bool
    {
        $query = static::where('collection_name', $collectionName);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return !$query->exists();
    }

    /**
     * Generate a unique collection name with suffix if needed
     */
    public static function generateUniqueCollectionName(string $organizationName, ?int $excludeId = null): string
    {
        $baseCollectionName = static::generateCollectionName($organizationName);
        $collectionName = $baseCollectionName;
        $counter = 1;
        
        while (!static::isCollectionNameAvailable($collectionName, $excludeId)) {
            $collectionName = $baseCollectionName . '_' . $counter;
            $counter++;
        }
        
        return $collectionName;
    }
}
