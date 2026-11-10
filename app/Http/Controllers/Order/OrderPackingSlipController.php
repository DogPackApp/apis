<?php

namespace App\Http\Controllers\Order;

use App\Models\Order\StoreOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class OrderPackingSlipController
{
    public function __invoke(Request $request, StoreOrder $order, string $size): \Illuminate\Http\Response|JsonResponse
    {
        try {

            $order = $order->load(['customer', 'store.address', 'items.product']);

            if ($order->order_status === StoreOrder::ORDER_STATUS_CANCELLED) {
                return response()->json([
                    'message' => "We can't generate Print Label for Cancelled Orders",
                ]);
            }

            if ($size === '4*7') {
                $widthInInches = 4;
                $heightInInches = 7;

                // Convert inches to points (1 inch = 72 points)
                $width = $widthInInches * 72;   // 288
                $height = $heightInInches * 72; // 504

                // 1. Generate PDF
                $pdf = Pdf::loadView('marketplace.pdfs.packing-slip-4x7', [
                    'order' => $order,
                ])->setPaper([0, 0, $width, $height]);
            } else {
                // 1. Generate PDF
                $pdf = Pdf::loadView('marketplace.pdfs.packing-slip', [
                    'order' => $order,
                ]);
            }

            // 2. Define file path
            $filename = "uploads/packing-slips/packing-slip-{$order->order_no}.pdf";

            // 3. Save PDF to S3
            Storage::disk('mps3')->put($filename, $pdf->output());

            // 4. Make it publicly visible
            Storage::disk('mps3')->setVisibility($filename, 'public');

            // 5. Get public URL
            $url = (config('app.env') === 'Production' ? config('services.marketplace_images_production') : config('services.marketplace_media')).$filename;

            // 6. Return response
            return response()->json([
                'pdf_url' => $url,
            ]);

        } catch (Exception $e) {

            if (app()->environment() !== 'production') {
                $message = $e->getMessage();
            } else {
                $message = 'Something went wrong';
            }

            return response()->json(['errors' => ['message' => $message]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
