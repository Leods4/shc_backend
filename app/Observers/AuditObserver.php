<?php

namespace App\Observers;

use App\Models\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditObserver
{
    /**
     * Handle the Model "created" event.
     */
    public function created(Model $model): void
    {
        $this->recordAudit($model, 'created', null, $model->getAttributes());
    }

    /**
     * Handle the Model "updated" event.
     */
    public function updated(Model $model): void
    {
        // Em um Observer 'updated', o modelo já foi salvo.
        // getChanges() retorna o array do que mudou.
        $newValues = $model->getChanges();

        // Remove chaves de sistema que não interessam (ex: updated_at)
        unset($newValues['updated_at']);

        if (empty($newValues)) {
            return;
        }

        // Pega os valores originais correspondentes às chaves que mudaram
        $oldValues = array_intersect_key($model->getOriginal(), $newValues);

        $this->recordAudit($model, 'updated', $oldValues, $newValues);
    }

    /**
     * Handle the Model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        $this->recordAudit($model, 'deleted', $model->getAttributes(), null);
    }

    /**
     * Método auxiliar para salvar no banco
     */
    protected function recordAudit(Model $model, string $event, ?array $oldValues, ?array $newValues): void
    {
        // Evita erro se rodar via seeder/tinker sem usuário logado
        $userId = Auth::id();

        Audit::create([
            'user_id'        => $userId,
            'event'          => $event,
            'auditable_type' => $model->getMorphClass(), // Ex: 'App\Models\User'
            'auditable_id'   => $model->getKey(),
            'old_values'     => $oldValues,
            'new_values'     => $newValues,
            'url'            => Request::fullUrl(),
            'ip_address'     => Request::ip(),
            'user_agent'     => Request::userAgent(),
        ]);
    }
}
