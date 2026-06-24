<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\KafkaProducerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private KafkaProducerService $kafka) {}

    public function index(): JsonResponse
    {
        return response()->json(Order::latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'total'         => 'required|numeric|min:0.01',
        ]);

        $order = Order::create([
            'customer_name' => $validated['customer_name'],
            'total'         => $validated['total'],
            'status'        => Order::STATUS_CREATED,
        ]);

        $this->kafka->publish('order.created', [
            'event'         => 'order.created',
            'order_id'      => $order->id,
            'customer_name' => $order->customer_name,
            'total'         => $order->total,
        ]);

        return response()->json([
            'id'     => $order->id,
            'status' => $order->status,
        ], 201);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json($order);
    }
}
