<?php

namespace App\Addons\Library\Models;

use App\Addons\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LibraryBook extends Model
{
    use BelongsToSchool, SoftDeletes;

    protected $table = 'library_books';
    protected $fillable = [
        'school_id', 'category_id', 'title', 'subtitle', 'author', 'publisher', 'isbn', 'edition',
        'publication_year', 'language', 'price', 'dewey_decimal_code', 'rack_location', 'shelf_number',
        'is_digital', 'digital_file_path', 'cover_image', 'description',
    ];
    protected $casts = ['is_digital' => 'boolean', 'price' => 'decimal:2'];
    protected $hidden = ['digital_file_path'];

    public function category()
    {
        return $this->belongsTo(LibraryCategory::class, 'category_id');
    }

    public function copies()
    {
        return $this->hasMany(LibraryBookCopy::class, 'book_id');
    }

    public function availableCopies()
    {
        return $this->copies()->where('status', LibraryBookCopy::AVAILABLE);
    }

    public function reservations()
    {
        return $this->hasMany(LibraryReservation::class, 'book_id');
    }

    public function getLocationAttribute(): ?string
    {
        $parts = array_filter([
            $this->rack_location ? 'Rack ' . $this->rack_location : null,
            $this->shelf_number ? 'Shelf ' . $this->shelf_number : null,
        ]);
        return $parts ? implode(', ', $parts) : null;
    }
}
