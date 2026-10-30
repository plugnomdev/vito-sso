<?php

namespace App\Vito\Plugins\Plugnomdev\VitoSso\Api;

use App\Actions\Server\CreateServer;
use App\Actions\Server\DeleteServer;
use App\Models\Server;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Called only by Plugnom's API (see VerifyPlugnomRequest). Customers are
 * ordinary Vito users, each with their own default project; instance
 * admins are never created, changed or signed into from here.
 */
class ApiController
{
    /** Vito's public key — Plugnom adds it to a new VPS at setup. */
    public function sshKey(): JsonResponse
    {
        return response()->json(['publicKey' => trim(get_public_key_content())]);
    }

    /** Find or create the customer's Vito account. */
    public function account(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();
        if ($user?->is_admin) {
            return response()->json(['error' => 'That email belongs to a Vito admin.'], 409);
        }

        $created = false;
        if (! $user) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                // Never used: customers only ever sign in through Plugnom.
                'password' => bcrypt(Str::random(64)),
                'timezone' => 'UTC',
                'is_admin' => false,
            ]);
            $created = true;
        }

        $project = $user->ensureHasDefaultProject();

        return response()->json(['userId' => $user->id, 'projectId' => $project->id, 'created' => $created], $created ? 201 : 200);
    }

    /** Attach a fresh VPS (Vito's key already on it) to the customer's project. */
    public function attachServer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'userId' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'ip' => ['required', 'ip'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'os' => ['required', 'string'],
            'services' => ['nullable', 'array'],
        ]);
        $user = $this->customer($data['userId']);
        $project = $user->ensureHasDefaultProject();

        try {
            $server = app(CreateServer::class)->create($user, $project, [
                'provider' => 'custom',
                'name' => $data['name'],
                'ip' => $data['ip'],
                'port' => $data['port'] ?? 22,
                'os' => $data['os'],
                'services' => $data['services'] ?? [],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Could not attach the server.', 'errors' => $e->errors()], 422);
        }

        return response()->json($this->serverJson($server), 201);
    }

    public function showServer(Request $request, int $server): JsonResponse
    {
        return response()->json($this->serverJson($this->customerServer($request, $server)));
    }

    /** Removes the server from Vito only — the VPS itself is untouched. */
    public function detachServer(Request $request, int $server): JsonResponse
    {
        $model = $this->customerServer($request, $server);
        app(DeleteServer::class)->delete($model, ['name' => $model->name]);

        return response()->json(null, 204);
    }

    private function customer(int $userId): User
    {
        $user = User::query()->find($userId);
        abort_if(! $user || $user->is_admin, 404, 'Customer not found.');

        return $user;
    }

    private function customerServer(Request $request, int $serverId): Server
    {
        $user = $this->customer((int) $request->query('userId'));
        $server = Server::query()->find($serverId);
        abort_if(! $server || ! $user->projects()->whereKey($server->project_id)->exists(), 404, 'Server not found.');

        return $server;
    }

    /** @return array<string, mixed> */
    private function serverJson(Server $server): array
    {
        return [
            'serverId' => $server->id,
            'projectId' => $server->project_id,
            'status' => $server->status instanceof \BackedEnum ? $server->status->value : $server->status,
            'progress' => $server->progress,
            'progressStep' => $server->progress_step,
        ];
    }
}
