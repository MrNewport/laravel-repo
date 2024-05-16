<?php
namespace MrNewport\LaravelRepo\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Repository
 *
 * @property int $id
 * @property string $name
 * @property string $full_name
 * @property string $html_url
 * @property string $description
 * @property string $readme
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Repository extends Model
{
    protected $fillable = [
        'name', 'full_name', 'html_url', 'description', 'readme'
    ];

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'name';
    }
}
