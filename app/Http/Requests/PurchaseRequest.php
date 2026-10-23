<?php

namespace App\Http\Requests;

use App\Enums\PurchaseStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $pricing = config('datecito.pricing');

        return [
            'store' => ['required', Rule::enum(PurchaseStore::class)],
            'product_id' => ['required', Rule::in([
                $pricing['monthly']['product_id'],
                $pricing['yearly']['product_id'],
                $pricing['super_like_pack']['product_id'],
            ])],
            'purchase_token' => ['required', 'string', 'max:4096'],
        ];
    }
}
