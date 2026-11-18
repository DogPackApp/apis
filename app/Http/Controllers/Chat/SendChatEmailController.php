<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Mail\Chat\ChatNewMessage;
use App\Models\Chat\ChatUser;
use App\Models\Store\Store;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class SendChatEmailController extends Controller
{
    public function __invoke(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => ['required'],
            'receiverType' => ['required', 'string', 'in:seller,customer'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $messageContent = $request->message;
        $receiverType = $request->receiverType;

        try {
            if ($receiverType == 'seller' && isset($messageContent['sellerUUID'])) {
                $store = Store::where('uuid', $messageContent['sellerUUID'])->with(['seller'])->first();

                $email = $store?->seller?->email ?? '';
                $receiverName = $store?->name ?? '';
                $messageData = (object) [
                    'name' => $messageContent['name'],
                    'message' => $messageContent['message'],
                ];
            } elseif ($receiverType == 'customer' && isset($messageContent['customerUUID']) && isset($messageContent['sellerUUID'])) {
                $store = Store::where('uuid', $messageContent['sellerUUID'])->first();
                $customer = ChatUser::where('uuid', $messageContent['customerUUID'])->first();

                $email = $customer?->email ?? '';
                $receiverName = $customer?->first_name ?? '';
                $messageData = (object) [
                    'name' => $store?->name ?? '',
                    'message' => $messageContent['message'],
                    'avatar' => $store?->image ?? null,
                    'customerEmail' => $email ? Crypt::encryptString($email) : null,
                ];
            } else {
                return response()->json(['errors' => ['message' => 'Invalid receiver type or id']], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            Mail::to($email, $receiverName)->send(new ChatNewMessage($receiverName, $messageData, $receiverType, $store));
        } catch (Exception $e) {
            DB::rollBack();

            if (app()->environment() !== 'production') {
                $message = $e->getMessage();
            } else {
                $message = 'Something went wrong';
            }

            return response()->json(['errors' => ['message' => $message]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
