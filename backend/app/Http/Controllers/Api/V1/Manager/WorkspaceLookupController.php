<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceListResource;
use App\Http\Resources\UserResource;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkspaceLookupController extends Controller
{
    public function clients(): AnonymousResourceCollection
    {
        return UserResource::collection(User::query()->where('role', 'client')->active()->orderBy('name')->get());
    }

    public function invoices(): AnonymousResourceCollection
    {
        return InvoiceListResource::collection(Invoice::query()->with(['project', 'client'])->latest()->paginate(15));
    }
}
