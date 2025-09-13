<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'action_type',
        'description',
        'aliases',
        'keywords',
        'source_type',
        'source_config',
        'params_template',
        'required_params',
        'optional_params',
        'min_score_threshold',
        'cache_ttl',
        'is_active',
        'roles_allowed',
        'response_template',
        'output_format',
    ];

    protected $casts = [
        'aliases' => 'array',
        'keywords' => 'array',
        'source_config' => 'array',
        'params_template' => 'array',
        'required_params' => 'array',
        'optional_params' => 'array',
        'roles_allowed' => 'array',
        'min_score_threshold' => 'decimal:3',
        'is_active' => 'boolean',
    ];

    /**
     * Get the organization that owns this action
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get text for vector embedding (description + aliases)
     */
    public function getTextForEmbedding(): string
    {
        $text = $this->description;
        
        if ($this->aliases && is_array($this->aliases)) {
            $text .= '. Aliases: ' . implode(', ', $this->aliases);
        }
        
        if ($this->keywords && is_array($this->keywords)) {
            $text .= '. Keywords: ' . implode(', ', $this->keywords);
        }
        
        return $text;
    }

    /**
     * Check if action can be executed for given user/role
     */
    public function canExecuteFor($user = null, $role = null): bool
    {
        if (!$this->is_active) {
            return false;
        }
        
        if (!$this->roles_allowed || empty($this->roles_allowed)) {
            return true; // No role restrictions
        }
        
        if ($role && in_array($role, $this->roles_allowed)) {
            return true;
        }
        
        // Add user role checking logic here if needed
        
        return false;
    }

    /**
     * Get source configuration for specific source type
     */
    public function getSourceConfig($key = null)
    {
        if ($key) {
            return $this->source_config[$key] ?? null;
        }
        
        return $this->source_config;
    }

    /**
     * Validate required parameters
     */
    public function validateParams(array $params): array
    {
        $missing = [];
        
        if ($this->required_params) {
            foreach ($this->required_params as $param) {
                if (!isset($params[$param]) || empty($params[$param])) {
                    $missing[] = $param;
                }
            }
        }
        
        return $missing;
    }

    /**
     * Scope to active actions only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by organization
     */
    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * Scope by source type
     */
    public function scopeBySourceType($query, $sourceType)
    {
        return $query->where('source_type', $sourceType);
    }
}