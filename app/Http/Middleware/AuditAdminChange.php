<?php

namespace App\Http\Middleware;

use App\Services\AdminService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuditAdminChange
{
    public function __construct(private AdminService $audit) {}

    public function handle(Request $request, Closure $next, string $action): Response
    {
        return DB::transaction(function () use ($request, $next, $action) {
            $response = $next($request);
            if (!$response->isSuccessful()) {
                // Roll back if the inner pipeline already rendered a controller exception.
                throw new HttpResponseException($response);
            }

            $result = $response->getData(true);
            if (($result['success'] ?? true) === false || ($result['updated'] ?? true) === false
                || ($result['marked_count'] ?? 1) === 0 || ($result['cleared_count'] ?? 1) === 0) {
                return $response;
            }

            $admin = $request->user();
            if (isset($result['count'])) {
                // Record only affected rows returned by the operation, including partial bulk matches.
                foreach ($result['deleted_ids'] ?? $result['data'] ?? [] as $item) {
                    $id = is_array($item) ? $item['id'] : $item;
                    $this->audit->logAction($admin, $action.' #'.$id);
                }
                return $response;
            }

            $params = $request->route()->parameters();
            $target = end($params);
            $id = $result['data']['id'] ?? ($target instanceof Model ? $target->getKey() : $target);
            $name = $result['data']['name'] ?? $result['data']['title'] ?? null;
            if ($target instanceof Model && !isset($result['data']['id']) && !$name) {
                $name = $target->name ?? $target->title;
            }
            $description = $id ? $action.' #'.$id : $action;
            $this->audit->logAction($admin, $name ? $description.' · '.$name : $description);
            return $response;
        });
    }
}
