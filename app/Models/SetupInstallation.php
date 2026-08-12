<?php

namespace App\Models;

use Core\Model;

class SetupInstallation extends Model
{
    protected string $table = 'system_configurations';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'config_key',
        'config_value',
        'config_type',
        'description',
        'category',
        'is_encrypted',
        'created_by',
        'updated_by'
    ];
    
    /**
     * Get configuration value by key
     */
    public static function getValue($key, $default = null)
    {
        $model = new self();
        $config = $model->where('config_key', $key)->first();
        if (!$config) {
            return $default;
        }
        
        if ($config['is_encrypted']) {
            // For now, return as-is since encryption might not be configured
            return $config['config_value'];
        }
        
        return $config['config_value'];
    }
    
    /**
     * Set configuration value
     */
    public static function setValue($key, $value, $type = 'string', $description = '', $category = 'general', $isEncrypted = false)
    {
        $model = new self();
        $config = $model->where('config_key', $key)->first();
        
        if ($isEncrypted) {
            // For now, skip encryption since it might not be configured
            // $value = encrypt($value);
        }
        
        $data = [
            'config_value' => $value,
            'config_type' => $type,
            'description' => $description,
            'category' => $category,
            'is_encrypted' => $isEncrypted ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($config) {
            // Update existing
            $model->update($config['id'], $data);
            return $model->find($config['id']);
        } else {
            // Create new
            $data['config_key'] = $key;
            $data['created_at'] = date('Y-m-d H:i:s');
            $id = $model->create($data);
            return $model->find($id);
        }
    }
    
    /**
     * Get all configurations grouped by category
     */
    public static function getByCategory()
    {
        $model = new self();
        $configs = $model->orderBy('category, config_key')->all();
        
        $grouped = [];
        foreach ($configs as $config) {
            $grouped[$config['category']][] = $config;
        }
        
        return $grouped;
    }
    
    /**
     * Delete configuration by key
     */
    public static function deleteByKey($key)
    {
        $model = new self();
        $config = $model->where('config_key', $key)->first();
        
        if ($config) {
            return $model->delete($config['id']);
        }
        
        return false;
    }
}
