<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Base controller for the versioned (v1) JSON API.
 *
 * Concrete API controllers extend this to inherit policy authorization
 * via {@see AuthorizesRequests::authorize()}.
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests;
}
