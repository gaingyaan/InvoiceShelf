<?php

namespace App\Platform\Ai\Models;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single chat-assistant thread, scoped to (company_id, user_id).
 *
 * One user in one company owns many conversations; messages belong to a
 * conversation. Conversations are deleted when either the company or the
 * user is deleted (cascade at the DB level).
 */
class AiConversation extends Model
{
    protected $table = 'ai_conversations';

    use HasFactory;

    protected $guarded = ['id'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id')->orderBy('created_at');
    }
}
