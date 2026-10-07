<?php

namespace Modules\Order\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Order\Actions\CreateEcommerceOrderAction;
use Modules\Order\Http\Requests\EcommerceOrderRequest;
// use Modules\Order\Http\Resources\OrderResource;

class EcommerceOrderController extends Controller
{
    public function __construct(
        protected CreateEcommerceOrderAction $createEcommerceOrder,
    ) {}

    /**
     * Place an order from the public storefront.
     *
     * This endpoint is publicly accessible. Guests may check out
     * without an account; the actor will be null in that case and
     * the order's audit trail will be marked is_system = true.
     */
    public function store(EcommerceOrderRequest $request)
    {
        $order = $this->createEcommerceOrder->execute(
            $request->validated(),
            Auth::user(),
        );

        // // Return the created order as JSON. The frontend can then redirect
        // // to its own confirmation page using the order uuid / number.
        // return (new OrderResource($order))
        //     ->response()
        //     ->setStatusCode(201);
        
        return to_route('shop.orders.confirmation', $order);
    }
}