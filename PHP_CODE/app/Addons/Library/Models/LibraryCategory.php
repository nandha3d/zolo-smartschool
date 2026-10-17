<?php

namespace App\Addons\Library\Models;

use App\Addons\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class LibraryCategory extends Model
{
    use BelongsToSchool;

    protected $table = 'library_categories';
    protected $fillable = ['school_id', 'name', 'code', 'parent_id', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function books()
    {
        return $this->hasMany(LibraryBook::class, 'category_id');
    }
}
