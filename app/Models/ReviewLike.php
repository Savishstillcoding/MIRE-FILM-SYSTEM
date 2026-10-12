<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** One user's ❤️ on one review. Table: review_likes. Toggled by ReviewController::like(). */
#[Fillable(['user_id'])]
class ReviewLike extends Model
{
}
