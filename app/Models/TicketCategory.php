<?php

namespace App\Models;

use App\Models\Concerns\FlushesReferenceCache;
use Illuminate\Database\Eloquent\Model;

class TicketCategory extends Model
{
    use FlushesReferenceCache;

    public const REFERENCE_CACHE_KEY = 'ticket_categories';

    protected $fillable = ['name'];
}
