<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;

use App\Models\CartModel;
use App\Models\CounterModel;
use App\Models\OrderModel;
use App\Models\ProductModel;
use App\Models\OrderItemsModel;
use App\Models\StockCartModel;
use App\Models\StockOrdersModel;
use App\Models\StockOrderItemsModel;
use App\Models\LogsModel;
use Illuminate\Validation\Rule;
use App\Models\SpecialRateModel;
use App\Models\JobCardModel;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\WishlistController;
use App\Utils\sendWhatsAppUtility;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;

class UpdateController extends Controller
{
    public function user(Request $request)
    {
        $get_user = Auth::id();

        $request->validate([
            // 'mobile' => ['required', 'string'],
            'password' => 'required',
            // 'name' => ['required', 'string'],
            
        ]);

        $update_user_record = User::where('id', $get_user)
        ->update([
            'password' => bcrypt($request->input('password')),
            // 'email' => strtolower($request->input('email')),
            // 'mobile' => $request->input('mobile'),
            // 'role' => $request->input('role'),
            // 'address_line_1' => $request->input('address_line_1'),
            // 'address_line_2' => $request->input('address_line_2'),
            // 'city' => $request->input('city'),
            // 'pincode' => $request->input('pincode'),
            // 'gstin' => $request->input('gstin'),
            // 'state' => $request->input('state'),
            // 'country' => $request->input('country'),
        ]);

        if ($update_user_record == 1) {
            return response()->json([
                'message' => 'User record updated successfully!',
                'data' => $update_user_record
            ], 200);
        }
        
        else {
            return response()->json([
                'message' => 'Failed to user record successfully'
            ], 400);
        }
    }

    public function updateUserType(Request $request)
    {
        // Validate the incoming request data
        $request->validate([
            'user_id' => 'required|exists:users,id', // Ensure that the user_id exists in the users table
            'type'    => 'required|string',
            'series'  => 'nullable|in:ss,mp',        // series can be null, ss, or mp
        ]);

        // Get the user_id, type and series from the request
        $user_id = $request->input('user_id');
        $type    = $request->input('type');
        $series  = $request->input('series'); // nullable

        // Prepare data to update based on series
        $updateData = [];

        // If series is not passed OR series is 'ss' => update `type` column only
        if (is_null($series) || $series === 'ss') {
            $updateData['type'] = $type;
        }
        // If series is 'mp' => update `mp_type` column only
        elseif ($series === 'mp') {
            $updateData['mp_type'] = $type;
        }

        // Safety check: if somehow nothing to update
        if (empty($updateData)) {
            return response()->json([
                'message' => 'No valid fields to update.',
            ], 400);
        }

        // Update the user record with the given data
        $update_user_record = User::where('id', $user_id)->update($updateData);

        // Check if the update was successful
        if ($update_user_record) {
            return response()->json([
                'message' => 'User record updated successfully!',
            ], 200);
        } else {
            return response()->json([
                'message' => 'Failed to update user record.',
            ], 400);
        }
    }

    public function updateUserSeries(Request $request)
    {
        // Validate the incoming request data
        $request->validate([
            'user_id' => 'required|exists:users,id',   // Ensure user exists
            'ss'      => 'nullable|in:0,1',           // Optional, must be 0 or 1 if present
            'mp'      => 'nullable|in:0,1',           // Optional, must be 0 or 1 if present
        ]);

        // At least one of ss or mp must be present
        if (!$request->has('ss') && !$request->has('mp')) {
            return response()->json([
                'message' => 'Please provide at least one of ss or mp to update.',
            ], 422);
        }

        $user_id = $request->input('user_id');

        $updateData = [];

        // Only update the fields that are actually passed in the request
        if ($request->has('ss')) {
            $updateData['ss'] = (int) $request->input('ss');  // cast to int 0/1
        }

        if ($request->has('mp')) {
            $updateData['mp'] = (int) $request->input('mp');  // cast to int 0/1
        }

        // If for some reason nothing to update, return error
        if (empty($updateData)) {
            return response()->json([
                'message' => 'No valid series fields provided to update.',
            ], 422);
        }

        $updated = User::where('id', $user_id)->update($updateData);

        if ($updated) {
            return response()->json([
                'message' => 'User series updated successfully!',
            ], 200);
        } else {
            return response()->json([
                'message' => 'Failed to update user series.',
            ], 400);
        }
    }

    public function inactivate_user(Request $request)
    {
        // Validate the incoming request data
        $request->validate([
            'user_id' => 'required|exists:users,id', // Ensure that the user_id exists in the users table
        ]);

        // Get the user_id from the request
        $user_id = $request->input('user_id');

        // Start a database transaction to ensure atomic updates
        DB::beginTransaction();

        try {
            // Inactivate the user (set is_verified to 0)
            $updateData = ['is_verified' => '0'];
            $update_user_record = User::where('id', $user_id)->update($updateData);

            // Remove all personal access tokens associated with this user
            PersonalAccessToken::where('tokenable_id', $user_id)->delete();

            // Commit the transaction if all operations are successful
            DB::commit();

            return response()->json([
                'message' => 'User inactivated and tokens removed successfully!',
            ], 200);

        } catch (\Exception $e) {
            // Rollback in case of any error
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to inactivate user and remove tokens.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function generate_otp(Request $request)
    {
        $request->validate([
            'mobile' => ['required', 'string'],
        ]);

        $mobile = $request->input('mobile');

        if (strlen($mobile) === 15) {
            // Remove 2nd and 3rd characters
            $mobile = substr($mobile, 0, 1) . substr($mobile, 3);
        }


        $get_user = User::select('id')
            ->where('mobile', $mobile)
            ->first();
            
        if (!$get_user == null) {

            $six_digit_otp_number = random_int(100000, 999999);

            $expiresAt = now()->addMinutes(10);

            $store_otp = User::where('mobile', $mobile)
                ->update([
                    'otp' => $six_digit_otp_number,
                    'expires_at' => $expiresAt,
                ]);
            
            if ($store_otp) {

                // $templateParams = [
                //     'name' => 'ace_otp', // Replace with your WhatsApp template name
                //     'language' => ['code' => 'en'],
                //     'components' => [
                //         [
                //             'type' => 'body',
                //             'parameters' => [
                //                 [
                //                     'type' => 'text',
                //                     'text' => $six_digit_otp_number,
                //                 ],
                //             ],
                //         ],
                //     ],
                // ];

                // // Directly create an instance of SendWhatsAppUtility
                // $whatsAppUtility = new sendWhatsAppUtility();

                // // Send OTP via WhatsApp
                // // $whatsAppUtility->sendOtp("+918961043773", $templateParams);
                // $response = $whatsAppUtility->sendWhatsApp("+918961043773", $templateParams, "+918961043773", 'OTP Campaign');
                $templateParams = [
                    'name' => 'ace_otp', // Replace with your WhatsApp template name
                    'language' => ['code' => 'en'],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                [
                                    'type' => 'text',
                                    'text' => $six_digit_otp_number,
                                ],
                            ],
                        ],
                        [
                            'type' => 'button',
                            'sub_type' => 'url',
                            "index" => "0",
                            'parameters' => [
                                [
                                    'type' => 'text',
                                    'text' => $six_digit_otp_number,
                                ],
                            ],
                        ]
                    ],
                ];
                
                // Directly create an instance of SendWhatsAppUtility
                $whatsAppUtility = new sendWhatsAppUtility();

                $sendTo = in_array($mobile, ['+919951263652', '+917981553591'], true)
                    ? '+917506691380'
                    : $mobile;
                
                // Send OTP via WhatsApp
                // $response = $whatsAppUtility->sendWhatsApp("+918961043773", $templateParams, "+918961043773", 'OTP Campaign');
                $response = $whatsAppUtility->sendWhatsApp($sendTo, $templateParams, $sendTo, 'OTP Campaign');
                
                // Send OTP via WhatsApp
                // $response = $this->whatsAppService->sendOtp("+918961043773", $templateParams);

                // dd($response);

                return response()->json([
                    'message' => 'Otp store successfully!',
                    'data' => $store_otp
                ], 200);
            }

        else {
                return response()->json([
                'message' => 'Fail to store otp successfully!',
                'data' => $store_otp
                ], 501);
            }
        }

        else {
            return response()->json([
                'message' => 'User has not registered!',
            ], 200);
            // no-register user will be registered as a guest user and otp will be send
            // $create_guest_user = User::create([
            //     'name' => "guest",
            //     'password' => bcrypt($request->input('mobile')),
            //     'mobile' => $request->input('mobile'),
            //     'type' => 'guest',
            //     'is_verified' => '0',
            // ]);

            // if (isset($create_guest_user)) {

            //     $mobileNumbers = User::where('role', 'admin')->pluck('mobile')->toArray();

            //     $templateParams = [
            //         'name' => 'ace_new_user_registered', // Replace with your WhatsApp template name
            //         'language' => ['code' => 'en'],
            //         'components' => [
            //             [
            //                 'type' => 'body',
            //                 'parameters' => [
            //                     [
            //                         'type' => 'text',
            //                         'text' => $create_guest_user->name,
            //                     ],
            //                     [
            //                         'type' => 'text',
            //                         'text' => $create_guest_user->mobile,
            //                     ],
            //                     [
            //                         'type' => 'text',
            //                         'text' => '-',
            //                     ],
            //                 ],
            //             ]
            //         ],
            //     ];
                
            //     $whatsAppUtility = new sendWhatsAppUtility();
                
            //     foreach ($mobileNumbers as $mobileNumber)
            //     {
            //         // Send message for each number
    
            //         $response = $whatsAppUtility->sendWhatsApp($mobileNumber, $templateParams, '', 'User Register');
    
            //         // Decode the response into an array
            //         $responseArray = json_decode($response, true);
    
            //         // Check if the response has an error or was successful
            //         if (isset($responseArray['error'])) 
            //         {
            //             echo "Failed to send message to Whatsapp!";
            //         } 
            //     }    

            //     $six_digit_otp_number = random_int(100000, 999999);

            //     $expiresAt = now()->addMinutes(10);

            //     $store_otp = User::where('mobile', $mobile)
            //                         ->update([
            //                             'otp' => $six_digit_otp_number,
            //                             'expires_at' => $expiresAt,
            //                         ]);
            //     if ($store_otp)     
            //     {

            //         $templateParams = [
            //                         'name' => 'ace_otp', // Replace with your WhatsApp template name
            //                         'language' => ['code' => 'en'],
            //                         'components' => [
            //                             [
            //                                 'type' => 'body',
            //                                 'parameters' => [
            //                                     [
            //                                         'type' => 'text',
            //                                         'text' => $six_digit_otp_number,
            //                                     ],
            //                                 ],
            //                             ],
            //                             [
            //                                 'type' => 'button',
            //                                 'sub_type' => 'url',
            //                                 "index" => "0",
            //                                 'parameters' => [
            //                                     [
            //                                         'type' => 'text',
            //                                         'text' => $six_digit_otp_number,
            //                                     ],
            //                                 ],
            //                             ]
            //                         ],
            //                     ];
                                
            //                     // Directly create an instance of SendWhatsAppUtility
            //                     $whatsAppUtility = new sendWhatsAppUtility();
                                
            //                     // Send OTP via WhatsApp
            //                     $response = $whatsAppUtility->sendWhatsApp($mobile, $templateParams, $mobile, 'OTP Campaign');
                
            //                     return response()->json([
            //                         'message' => 'Otp store successfully!',
            //                         'data' => $store_otp
            //                     ], 200);
            //     }

            //     else {
            //         return response()->json([
            //         'message' => 'Fail to store otp successfully!',
            //         'data' => $store_otp
            //         ], 501);
            //     }
            // }

            // else {
            //     return response()->json([
            //         'message' => 'Sorry, please Try Again!',
            //     ], 500);
            // }  
        }
    }

    public function cart(Request $request, $id)
    {
        $get_user = Auth::User();
        
        if($get_user->role == 'admin')
        {
            $request->validate([
                'user_id' => 'required',
                // 'products_id' => 'required',
                'product_code' => 'required',
                'rate' => 'required',
                'quantity' => 'required',
                // 'amount' => 'required',
                'type' => 'required',
            ]);
    
            $update_cart = CartModel::where('id', $id)
            ->update([
                // 'products_id' => $request->input('products_id'),
                'product_code' => $request->input('product_code'),
                'quantity' => $request->input('quantity'),
                'rate' => $request->input('rate'),
                'amount' => ($request->input('rate')) * ($request->input('quantity')),
                'type' => $request->input('type'),
                'remarks' => $request->input('remarks'),
                'size' => $request->input('size'),
            ]);
        }
        else {
            $request->validate([
                // 'products_id' => 'required',
                'product_code' => 'required',
                // 'rate' => 'required',
                'quantity' => 'required',
                // 'amount' => 'required',
                'type' => 'required',
            ]);
    
                $update_cart = CartModel::where('id', $id)
                ->update([
                    // 'products_id' => $request->input('products_id'),
                    'product_code' => $request->input('product_code'),
                    'quantity' => $request->input('quantity'),
                    'amount' => ($request->input('rate')) * ($request->input('quantity')),
                    'type' => $request->input('type'),
                    'remarks' => $request->input('remarks'),
                    'size' => $request->input('size'),
                ]);
        }

        if ($update_cart == 1) {
            return response()->json([
                'message' => 'Cart updated successfully!',
                'data' => $update_cart
            ], 200);
        }

        else {
            return response()->json([
                'message' => 'Failed to update cart successfully!'
            ], 400);
        }    
    }

    public function verify_user(Request $request, $get_id)
    {
        $request->validate([
            'type' => 'nullable|string|regex:/^[a-zA-Z\s]*$/',
        ]);

        $update_verify = User::where('id', $get_id)
             ->update([
                 'is_verified' => '1',
                 'type' => $request->input('type'),
             ]);

             $user = User::select('name', 'mobile')
                          ->where('id', $get_id)
                          ->first();
            
            // $mobileNumbers = User::where('role', 'admin')->pluck('mobile')->toArray();

            // Find the user by ID and toggle is_verified
            //$user = User::findOrFail($get_id);
            //$user->is_verified = '1';
            //$user->type = $request->input('type');
            //$update_verify = $user->save();

            // Retrieve the name and mobile of the user
            //$userData = $user->only(['name', 'mobile']);


            if ($update_verify == 1) {

                $templateParams = [
                    'name' => 'ace_user_approved', // Replace with your WhatsApp template name
                    'language' => ['code' => 'en'],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                [
                                    'type' => 'text',
                                    'text' => $user->name,
                                ],
                                [
                                    'type' => 'text',
                                    'text' => substr($user->mobile, -10),
                                ],
                            ],
                        ]
                    ],
                ];
                
                // Directly create an instance of SendWhatsAppUtility
                $whatsAppUtility = new sendWhatsAppUtility();
                
                $response = $whatsAppUtility->sendWhatsApp($user->mobile, $templateParams, '', 'Approve Client');
                $response = $whatsAppUtility->sendWhatsApp('918961043773', $templateParams, '', 'Approve Client');
                $response = $whatsAppUtility->sendWhatsApp('919966633307', $templateParams, '', 'Approve Client');

                // Decode the response into an array
                $responseArray = json_decode($response, true);

                // Check if the response has an error or was successful
                if (isset($responseArray['error'])) {
                    return response()->json([
                        'message' => 'Error!',
                    ], 503);
                } else 
                {
                    return response()->json([
                         'message' => 'User verified successfully!',
                         'data' => $update_verify
                     ], 200);
                  // Check if the user is now verified or unverified
                    //$statusMessage = $user->is_verified == 1 ? 'User verified successfully!' : 'User unverified successfully!';

                    //return response()->json([
                        //'message' => $statusMessage,
                        //'data' => $user->is_verified, // Returns the is_verified status (1 or 0)
                    //], 200);
                }
            }
    
            else {
                return response()->json([
                    'message' => 'Sorry, failed to update'
                ], 400);
            }    
    }

    public function unverify_user(Request $request, $get_id)
    {

        $update_verify = User::where('id', $get_id)
             ->update([
                 'is_verified' => '0',
             ]);

             $user = User::select('name', 'mobile')
                          ->where('id', $get_id)
                          ->first();

            if ($update_verify == 1) {

                return response()->json([
                        'message' => 'User verified successfully!',
                        'data' => $update_verify
                    ], 200);
            }
    
            else {
                return response()->json([
                    'message' => 'Sorry, failed to update'
                ], 400);
            }    
    }

    public function updatePurchaseLock(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'purchase_lock' => 'required|in:0,1',  // Ensure purchase_lock is either 0 or 1
            'user_id' => 'required|exists:users,id', // Ensure user_id exists in the users table
        ]);

        try {
            // Find the user by ID
            $user = User::findOrFail($request->input('user_id'));

            // Update the purchase_lock value
            $user->purchase_lock = $validated['purchase_lock'];

            // Save the changes
            $user->save();

            // Return a success response
            return response()->json([
                'success' => true,
                'message' => 'Purchase lock status updated successfully.',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            // Return an error response if anything goes wrong
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the purchase lock status.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // public function edit_order(Request $request, $id)
    // {
    //     $get_user = Auth::User();

    //     $request->validate([
    //         'order_id' => 'required|string',
    //         'order_type' => 'required|string',
    //         'user_id' => 'required|integer',
    //         'amount' => 'required|numeric',
    //         'items' => 'required|array',
    //         'items.*.product_code' => 'required|string',
    //         'items.*.product_name' => 'required|string',
    //         'items.*.quantity' => 'required|integer',
    //         'items.*.orig_quantity' => 'nullable|integer',
    //         'items.*.rate' => 'required|numeric',
    //         'items.*.total' => 'required|numeric',
    //         'items.*.remarks' => 'nullable|string',
    //         'items.*.markedForDeletion' => 'nullable|boolean',
    //         'items.*.removalReason' => 'nullable|string',
    //         'cancel_order_id' => 'nullable|string',
    //     ]);

    //     $order = OrderModel::find($id);

    //     if (!$order) {
    //         return response()->json(['message' => 'Order not found!'], 404);
    //     }

    //     if ($order->user_id !== $request->input('user_id')) {
    //         return response()->json([
    //             'message' => 'Unauthorized action. This order does not belong to the specified user.'
    //         ], 403);
    //     }
        
    //     $cancelOrderIds = array_filter(array_map('trim', explode(',', $request->input('cancel_order_id'))));

    //     $is_merged = false;
    //     $merged_orders = '';
    //     DB::beginTransaction();

    //     try {
            
    //         if (!empty($cancelOrderIds)) {
    //             foreach ($cancelOrderIds as $cancelId) {
                    
    //                 $cancelOrder = OrderModel::find($cancelId);
    //                 if ($cancelOrder) {
    //                     $cancelOrder->status = "cancelled";
    //                     $cancelOrder->save();

    //                     // Append the cancelled order_id to the merged_orders string
    //                     if ($merged_orders) {
    //                         $merged_orders .= ', '; // Add a comma separator between IDs
    //                     }
    //                     $merged_orders .= $cancelOrder->order_id; // Add the current order_id
    //                 } else {
    //                     Log::warning("Cancel Order ID {$cancelId} not found");
    //                 }
    //                 $is_merged = true;
    //             }
    //         }

    //         $existingItems = OrderItemsModel::where('order_id', $id)->get()->keyBy('product_code');
    //         OrderItemsModel::where('order_id', $id)->delete();

    //         $user_id = $order->user_id;
    //         $user_type = User::select('type')->where('id', $user_id)->first();
    //         $items = $request->input('items');
    //         $calculatedAmount = 0;

    //         foreach ($items as $item) {
    //             if ($item['markedForDeletion'] ?? false) {
    //                 if (($item['removalReason'] ?? '') === 'Not in stock') {
    //                     $wishlistController = new WishlistController();
    //                     $wishlistController->saveToWishlist($user_id, $item);
    //                 }
    //                 continue;
    //             }

    //             $product = ProductModel::where('product_code', $item['product_code'])->first();
    //             if (!$product) {
    //                 throw new \Exception("Product {$item['product_code']} not found.");
    //             }

    //             OrderItemsModel::create([
    //                 'order_id' => $id,
    //                 'product_code' => $item['product_code'],
    //                 'product_name' => $item['product_name'],
    //                 'quantity' => $item['quantity'],
    //                 'rate' => $item['rate'],
    //                 'total' => $item['total'],
    //                 'type' => strtolower($request->input('order_type')),
    //                 'remarks' => $item['remarks'] ?? '',
    //             ]);

    //             $calculatedAmount += $item['total'];
    //         }

    //         // Set order amount based on sum of item totals, not input
    //         $order->amount = $calculatedAmount;
    //         $order->save();

    //         // if ($get_user->mobile != "+918961043773") {
    //             $generate_order_invoice = new InvoiceController();
    //             $generate_order_invoice->generateorderInvoice($id, true, $is_merged, $merged_orders);
    //             $generate_order_invoice->generatePackingSlip($id, true);
    //         // }

    //         DB::commit();

    //         return response()->json([
    //             'message' => 'Order updated successfully!',
    //             'order' => $order,
    //             'items' => $items
    //         ], 200);

    //     } catch (\Exception $e) {
    //         DB::rollBack();

    //         Log::error('Failed to edit order', [
    //             'order_id' => $id,
    //             'user_id' => $get_user->id ?? null,
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);

    //         return response()->json([
    //             'message' => 'Failed to update order.',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    // public function edit_order(Request $request, $id)
    // {
    //     $get_user = Auth::user();

    //     // Log the raw request
    //     LogsModel::create([
    //         'function'   => 'edit_order',
    //         'request'    => json_encode([
    //             'params'  => $request->all(),
    //             'order_id'=> $id,
    //             'user_id' => Auth::id()
    //         ]),
    //         'created_at' => now(),
    //     ]);

    //     die();

    //     if (is_numeric($request->input('order_id'))) {
    //         $internalId = (int) $request->input('order_id');
    //         $dbOrder = OrderModel::select('order_id')->where('id', $internalId)->first();
        
    //         if ($dbOrder) {
    //             $request->merge([
    //                 'order_id' => $dbOrder->order_id
    //             ]);
    //         } else {
    //             return response()->json(['message' => 'Order ID not found for numeric input.'], 404);
    //         }
    //     }
        
    //     $request->validate([
    //         'order_id' => 'required|string',
    //         'order_type' => 'required|string',
    //         'user_id' => 'required|integer',
    //         'amount' => 'required|numeric',
    //         'items' => 'required|array',
    //         'items.*.product_code' => 'required|string',
    //         'items.*.product_name' => 'required|string',
    //         'items.*.quantity' => 'required|integer',
    //         'items.*.rate' => 'required|numeric',
    //         'items.*.total' => 'required|numeric',
    //         'items.*.remarks' => 'nullable|string',
    //         'items.*.markedForDeletion' => 'nullable|boolean',
    //         'items.*.removalReason' => 'nullable|string',
    //         'items.*.orig_quantity' => 'nullable|integer',
    //         'cancel_order_id' => 'nullable|string',
    //         'edit_type' => 'nullable|in:edit,merge,split',
    //     ]);

    //     $editType = strtolower($request->input('edit_type', 'edit'));
    //     $cancelOrderIds = array_filter(array_map('trim', explode(',', $request->input('cancel_order_id'))));
    //     $order = OrderModel::find($id);

    //     if (!$order) {
    //         return response()->json(['message' => 'Order not found!'], 404);
    //     }

    //     if ($order->user_id !== $request->input('user_id')) {
    //         return response()->json(['message' => 'Unauthorized action.'], 403);
    //     }

    //     $is_merged = false;
    //     $merged_orders = '';
    //     $is_split = false;
    //     $old_order_id = '';
    //     $newOrder = null;

    //     DB::beginTransaction();

    //     try {
    //         if ($editType === 'merge' && !empty($cancelOrderIds)) {
    //             foreach ($cancelOrderIds as $cancelId) {
    //                 $cancelOrder = OrderModel::find($cancelId);
    //                 if ($cancelOrder) {
    //                     $cancelOrder->status = "cancelled";
    //                     $cancelOrder->save();
    //                     $merged_orders .= ($merged_orders ? ', ' : '') . $cancelOrder->order_id;
    //                     $is_merged = true;
    //                 } else {
    //                     Log::warning("Cancel Order ID {$cancelId} not found");
    //                 }
    //             }
    //         }

    //         if ($editType === 'split') {

    //             Log::info('Split operation started', ['order_id' => $order->id]);

    //             $splitItems = [];
    //             $keepItems = [];
    //             $splitTotal = 0;
    //             $keepTotal = 0;

    //             foreach ($request->items as $item) {
    //                 Log::debug('Processing item', [
    //                     'product_code' => $item['product_code'],
    //                     'orig_quantity' => $item['orig_quantity'] ?? null,
    //                     'quantity' => $item['quantity'],
    //                     'markedForDeletion' => $item['markedForDeletion'] ?? false
    //                 ]);

    //                 $moveQty = ($item['markedForDeletion'] ?? false)
    //                     ? $item['orig_quantity']
    //                     : max(0, ($item['orig_quantity'] ?? 0) - $item['quantity']);

    //                 if ($moveQty > 0) {
    //                     $splitItems[] = array_merge($item, ['quantity' => $moveQty, 'total' => $moveQty * $item['rate']]);
    //                     $splitTotal += ($moveQty * $item['rate']);
    //                 }

    //                 $keptQty = $item['quantity'];
    //                 if ($keptQty > 0 && !($item['markedForDeletion'] ?? false)) {
    //                     $keepItems[] = array_merge($item, ['quantity' => $keptQty, 'total' => $keptQty * $item['rate']]);
    //                     $keepTotal += ($keptQty * $item['rate']);
    //                 }
    //             }

    //             if (count($splitItems) > 0) {
    //                 $baseOrderId = $order->order_id;

    //                 if (preg_match('/^(.*?)(SPL(\d+)?)?$/', $baseOrderId, $matches)) {
    //                     $base = $matches[1];
    //                     $suffix = isset($matches[3]) ? intval($matches[3]) + 1 : 2;
    //                     $newOrderCode = $base . 'SPL' . $suffix;
    //                 } else {
    //                     $newOrderCode = $baseOrderId . 'SPL2';
    //                 }

    //                 Log::info('Creating new split order', ['base_order_id' => $order->order_id]);

    //                 $newOrder = OrderModel::create([
    //                     'user_id' => $order->user_id,
    //                     'order_id' => $newOrderCode,
    //                     'order_date' => Carbon::now(),
    //                     'amount' => $splitTotal,
    //                     'type' => $order->type,
    //                 ]);

    //                 foreach ($splitItems as $item) {
    //                     Log::debug('Creating item in split order', ['product_code' => $item['product_code'], 'quantity' => $item['quantity']]);

    //                     OrderItemsModel::create([
    //                         'order_id' => $newOrder->id,
    //                         'product_code' => $item['product_code'],
    //                         'product_name' => $item['product_name'],
    //                         'rate' => $item['rate'],
    //                         'quantity' => $item['quantity'],
    //                         'total' => $item['total'],
    //                         'type' => strtolower($request->input('order_type')),
    //                         'remarks' => $item['remarks'] ?? '',
    //                         'size' => $item['size'] ?? null,
    //                     ]);
    //                 }

    //                 $is_split = true;
    //                 $old_order_id = $order->order_id;

    //                 Log::info('Updating original order amount', ['keep_total' => $keepTotal]);

    //                 $order->update(['amount' => $keepTotal]);
    //                 $request->merge(['items' => $keepItems]);
    //             }
    //         }

    //         OrderItemsModel::where('order_id', $id)->delete();
    //         $calculatedAmount = 0;

    //         foreach ($request->items as $item) {
    //             if (($item['markedForDeletion'] ?? false) && $editType !== 'split') {
    //                 if (($item['removalReason'] ?? '') === 'Not in stock') {
    //                     (new WishlistController)->saveToWishlist($order->user_id, $item);
    //                 }
    //                 continue;
    //             }

    //             OrderItemsModel::create([
    //                 'order_id' => $id,
    //                 'product_code' => $item['product_code'],
    //                 'product_name' => $item['product_name'],
    //                 'quantity' => $item['quantity'],
    //                 'rate' => $item['rate'],
    //                 'total' => $item['total'],
    //                 'type' => strtolower($request->input('order_type')),
    //                 'remarks' => $item['remarks'] ?? '',
    //                 'size' => $item['size'] ?? null,
    //             ]);

    //             $calculatedAmount += $item['total'];
    //         }

    //         $order->amount = $calculatedAmount;
    //         $order->save();

    //         $invoiceController = new InvoiceController();
    //         $invoiceController->generateorderInvoice($order->id, [
    //             'is_edited' => true,
    //             'is_merged' => $is_merged,
    //             'merged_orders' => $merged_orders,
    //             'is_split' => false,
    //             'old_order_id' => ''
    //         ]);
    //         $invoiceController->generatePackingSlip($order->id, true);

    //         if ($is_split && $newOrder) {
    //             $invoiceController->generateorderInvoice($newOrder->id, [
    //                 'is_edited' => true,
    //                 'is_split' => true,
    //                 'old_order_id' => $old_order_id,
    //             ]);
    //             $invoiceController->generatePackingSlip($newOrder->id, true);
    //         }

    //         DB::commit();

    //         return response()->json([
    //             'message' => 'Order updated successfully!',
    //             'order' => $order->fresh('order_items'),
    //             'new_order' => $newOrder ?? null,
    //         ]);

    //     } catch (\Exception $e) {
    //         DB::rollBack();

    //         Log::error('Failed to edit order', [
    //             'order_id' => $id,
    //             'user_id' => $get_user->id ?? null,
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);

    //         return response()->json([
    //             'message' => 'Failed to update order.',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    /**
     * Generate the next split order code.
     * - Root is the base order code with any trailing SPL# removed.
     * - Scans DB for siblings: {root}SPL\d+
     * - Returns {root}SPL{nextNumber}, starting from 2.
     */
    private function nextSplitCode(string $baseOrderCode): string
    {
        // 1) Normalize to a root without SPL#
        $root = preg_replace('/SPL\d+$/', '', $baseOrderCode);

        // 2) Get all existing siblings like {root}SPL%
        $existing = OrderModel::where('order_id', 'like', $root.'SPL%')
            ->pluck('order_id')
            ->all();

        // 3) Find max existing SPL number (default 1 → next becomes 2)
        $max = 1;
        $pattern = '/^'.preg_quote($root, '/').'SPL(\d+)$/';
        foreach ($existing as $code) {
            if (preg_match($pattern, $code, $m)) {
                $n = (int) $m[1];
                if ($n > $max) $max = $n;
            }
        }

        return $root . 'SPL' . ($max + 1);
    }

    /**
     * Consolidate duplicate items:
     * Merge rows that share product_code + rate + size + type (+ optional flags),
     * sum quantities, recompute totals, and merge unique remarks.
     */
    private function consolidateLineItems(array $items): array
    {
        $bucket = [];

        foreach ($items as $it) {
            $code = (string)($it['product_code'] ?? '');
            $rate = (float)($it['rate'] ?? 0);
            $size = array_key_exists('size', $it) ? (string)$it['size'] : null;
            $type = strtolower((string)($it['type'] ?? ''));

            // You can extend the key if you also want to keep useGstPrice separate:
            // $useGst = (bool)($it['useGstPrice'] ?? false);
            // $key = implode('|', [$code, $rate, $size ?? '', $type, (int)$useGst]);

            $key = implode('|', [$code, $rate, $size ?? '', $type]);

            if (!isset($bucket[$key])) {
                // initialize
                $bucket[$key] = $it;
                $bucket[$key]['quantity'] = (int)($it['quantity'] ?? 0);
                $bucket[$key]['total']    = round($bucket[$key]['quantity'] * $rate, 2);
                // normalize remarks holder
                $bucket[$key]['remarks']  = trim((string)($it['remarks'] ?? ''));
            } else {
                // accumulate
                $bucket[$key]['quantity'] += (int)($it['quantity'] ?? 0);
                $bucket[$key]['total']     = round($bucket[$key]['quantity'] * $rate, 2);

                // merge unique remarks (keep short and clean)
                $curr = trim((string)($it['remarks'] ?? ''));
                if ($curr !== '') {
                    $existing = $bucket[$key]['remarks'];
                    if ($existing === '') {
                        $bucket[$key]['remarks'] = $curr;
                    } elseif (stripos($existing, $curr) === false) {
                        $bucket[$key]['remarks'] = $existing.' | '.$curr;
                    }
                }
            }
        }

        // Return as list
        return array_values($bucket);
    }



    /**
     * Dated : 07-09-2025
     * Edit/Merge/Split an order.
     * The request body is DIRECT (no "params" wrapper).
     *
     * Expected top-level fields (examples from your message):
     * - order_id: string|int (external code like "SS/ORG/560/25-26" OR numeric internal id; numeric will be resolved to code)
     * - order_type: "Gst" | ...
     * - user_id: int (owner of the order)
     * - amount: number (client-side calc; server will recalc)
     * - items: array<item>
     * - cancel_order_id: string CSV of INTERNAL ids (only for merge), e.g. "2010,2011"
     * - edit_type: "edit" | "merge" | "split"
     *
     * Item:
     * - product_code (req), product_name (req), rate (req), quantity (req), total (req), type (req)
     * - orig_quantity (recommended/required for merge/split math)
     * - markedForDeletion (bool), removalReason (string|null)
     * - markedForSplit (bool)
     * - size, remarks, product_image, specialInstruction, useGstPrice (optional passthrough)
     */
    public function edit_order(Request $request, $id)
    {
        $authUserId = Auth::id();
        $payload    = $request->all(); // <<-- DIRECT (no "params")

        // 0) Forensics log (raw)
        LogsModel::create([
            'function'   => 'edit_order',
            'request'    => json_encode([
                'params'   => $payload,
                'order_id' => $id,
                'user_id'  => $authUserId,
            ]),
            'created_at' => now(),
        ]);

        // 1) Resolve numeric order_id (client may send internal id)
        if (isset($payload['order_id']) && is_numeric($payload['order_id'])) {
            $internalId = (int) $payload['order_id'];
            $row = OrderModel::select('order_id')->where('id', $internalId)->first();
            if (!$row) {
                return response()->json(['message' => 'Order ID not found for numeric input.'], 404);
            }
            $payload['order_id'] = $row->order_id;
        }

        // 2) Validate request
        $validator = \Validator::make($payload, [
            'order_id'              => 'required',
            'order_type'            => 'required|string',
            'user_id'               => 'required|integer',
            'amount'                => 'required|numeric',
            'items'                 => 'required|array|min:0',
            'items.*.product_code'  => 'required|string',
            'items.*.product_name'  => 'required|string',
            'items.*.quantity'      => 'required|integer|min:0',
            'items.*.rate'          => 'required|numeric|min:0',
            'items.*.total'         => 'required|numeric|min:0',
            'items.*.remarks'       => 'nullable|string',
            'items.*.size'          => 'nullable|string',
            'items.*.markedForDeletion' => 'nullable|boolean',
            'items.*.markedForSplit'    => 'nullable|boolean',
            'items.*.removalReason'     => 'nullable|string',
            'items.*.orig_quantity'     => 'nullable|integer|min:0',
            'cancel_order_id'       => 'nullable|string',
            'edit_type'             => 'nullable|in:edit,merge,split',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // 3) Load target order by INTERNAL id from route
        /** @var OrderModel|null $order */
        $order = OrderModel::find($id);
        if (!$order) {
            return response()->json(['message' => 'Order not found!'], 404);
        }
        if ((int)$payload['user_id'] !== (int)$order->user_id) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $editType       = strtolower($payload['edit_type'] ?? 'edit');
        $orderTypeLower = strtolower($payload['order_type']);

        // Parse cancel list (MERGE only) — expects INTERNAL ids comma-separated
        $cancelIds = [];
        if ($editType === 'merge' && !empty($payload['cancel_order_id'])) {
            $cancelIds = array_filter(array_map('trim', explode(',', $payload['cancel_order_id'])));
        }

        $isMerged         = false;
        $mergedOrderCodes = '';
        $createdSplit     = false;
        $splitFromCode    = '';
        $newSplitOrder    = null;

        // // Helper: Next SPL code: base + SPL2/SPL3...
        // $nextSplitCode = function (string $baseOrderCode): string {
        //     if (preg_match('/^(.*?)(SPL(\d+)?)$/', $baseOrderCode, $m)) {
        //         $base   = $m[1];
        //         $suffix = isset($m[3]) ? ((int)$m[3] + 1) : 2;
        //         return $base . 'SPL' . $suffix;
        //     }
        //     return $baseOrderCode . 'SPL2';
        // };

        // Target collections
        $keepItems      = []; // Items that remain in CURRENT order
        $splitItems     = []; // Items moved to NEW split order (if any)
        $newTotalKeep   = 0.0;
        $newTotalSplit  = 0.0;

        DB::beginTransaction();
        try {
            /**
             * 4A) MERGE: cancel other orders (INTERNAL ids)
             */
            if ($editType === 'merge' && !empty($cancelIds)) {
                foreach ($cancelIds as $cancelInternalId) {
                    /** @var OrderModel|null $cancelOrder */
                    $cancelOrder = OrderModel::find($cancelInternalId);
                    if (!$cancelOrder) {
                        Log::warning("Merge: cancel target not found", ['id' => $cancelInternalId]);
                        continue;
                    }
                    $cancelOrder->status = 'cancelled';
                    $cancelOrder->save();

                    $mergedOrderCodes .= ($mergedOrderCodes ? ', ' : '') . $cancelOrder->order_id;
                    $isMerged = true;
                }
            }

            /**
             * 4B) Build "keep" vs "split" sets based on rules
             *
             * Rules summary:
             *  - EDIT:
             *      * if markedForDeletion = true → remove from CURRENT (optionally wishlist if "Not in stock")
             *      * else keep with its new quantity
             *
             *  - MERGE:
             *      * markedForSplit = true → move (orig - quantity) to SPL order.
             *          - if orig == quantity → remove from CURRENT
             *          - else keep CURRENT with 'quantity' and split the delta
             *      * markedForDeletion = true → remove delta (orig - quantity); if orig == quantity → remove item fully
             *          - we simply keep the 'quantity' in CURRENT. If this leaves 0, it's effectively removed.
             *          - if removalReason == "Not in stock" → save to wishlist
             *      * else → keep as-is (with new quantity)
             *
             *  - SPLIT:
             *      * Only split via markedForSplit
             *      * markedForSplit = true → move (orig - quantity) to SPL order
             *          - if orig == quantity → remove from CURRENT
             *          - else keep CURRENT with 'quantity'
             *      * markedForDeletion is ignored in split scenario (by your note)
             */
            foreach ($payload['items'] as $item) {
                $orig = isset($item['orig_quantity']) ? (int)$item['orig_quantity'] : (int)$item['quantity'];
                $qty  = (int)$item['quantity'];
                $rate = (float)$item['rate'];

                $markedDel   = (bool)($item['markedForDeletion'] ?? false);
                $markedSplit = (bool)($item['markedForSplit']   ?? false);

                $keepQty  = 0;
                $moveQty  = 0;

                if ($editType === 'edit') {
                    if ($markedDel) {
                        if (($item['removalReason'] ?? '') === 'Not in stock') {
                            // move to wishlist (uses your existing controller)
                            (new WishlistController)->saveToWishlist($order->user_id, $item);
                        }
                        // do not keep
                        $keepQty = 0;
                    } else {
                        $keepQty = max(0, $qty);
                    }
                }
                elseif ($editType === 'merge') {
                    if ($markedSplit) {
                        $moveQty = max(0, $orig - $qty);
                        // if fully moved (orig == qty), we remove from current
                        $keepQty = ($orig === $qty) ? 0 : max(0, $qty);
                    } elseif ($markedDel) {
                        // remove delta (orig - qty). Keep the 'qty' as remaining in current.
                        if (($item['removalReason'] ?? '') === 'Not in stock') {
                            (new WishlistController)->saveToWishlist($order->user_id, $item);
                        }
                        $keepQty = max(0, $qty);
                        $moveQty = 0; // deletion, not split
                    } else {
                        $keepQty = max(0, $qty);
                    }
                }
                else { // split
                    if ($markedSplit) {
                        $moveQty = max(0, $orig - $qty);
                        $keepQty = ($orig === $qty) ? 0 : max(0, $qty);
                    } else {
                        // In split mode, only markedForSplit causes movement.
                        $keepQty = max(0, $qty);
                    }
                }

                // Add kept part
                if ($keepQty > 0) {
                    $keepItem = $item;
                    $keepItem['quantity'] = $keepQty;
                    $keepItem['total']    = $keepQty * $rate;
                    $keepItems[] = $keepItem;
                    $newTotalKeep += $keepItem['total'];
                }

                // Add split part
                if ($moveQty > 0) {
                    $splitItem = $item;
                    $splitItem['quantity'] = $moveQty;
                    $splitItem['total']    = $moveQty * $rate;
                    $splitItems[] = $splitItem;
                    $newTotalSplit += $splitItem['total'];
                }
            }

            // --- consolidate duplicate lines ---
            $keepItems  = $this->consolidateLineItems($keepItems);
            $splitItems = $this->consolidateLineItems($splitItems);

            // --- recompute order totals from consolidated items ---
            $newTotalKeep  = array_sum(array_column($keepItems, 'total'));
            $newTotalSplit = array_sum(array_column($splitItems, 'total'));

            /**
             * 4C) Upsert current order items: replace all with the "keep" set
             */
            OrderItemsModel::where('order_id', $order->id)->delete();

            foreach ($keepItems as $ki) {
                OrderItemsModel::create([
                    'order_id'      => $order->id,
                    'product_code'  => $ki['product_code'],
                    'product_name'  => $ki['product_name'],
                    'quantity'      => (int)$ki['quantity'],
                    'rate'          => (float)$ki['rate'],
                    'total'         => (float)$ki['total'],
                    'type'          => $orderTypeLower,
                    'remarks'       => $ki['remarks'] ?? '',
                    'size'          => $ki['size'] ?? null,
                ]);
            }

            $order->amount = $newTotalKeep;
            $order->save();

            /**
             * 4D) Create a SPL order if we have any split items (both MERGE & SPLIT modes)
             */
            if (!empty($splitItems)) {
                $baseCode      = $order->order_id;
                $newOrderCode  = $this->nextSplitCode($baseCode);
                $splitFromCode = $baseCode;

                $newSplitOrder = OrderModel::create([
                    'user_id'   => $order->user_id,
                    'order_id'  => $newOrderCode,
                    'order_date'=> Carbon::now(),
                    'amount'    => $newTotalSplit,
                    'type'      => $order->type, // keep type aligned
                ]);

                foreach ($splitItems as $si) {
                    OrderItemsModel::create([
                        'order_id'      => $newSplitOrder->id,
                        'product_code'  => $si['product_code'],
                        'product_name'  => $si['product_name'],
                        'quantity'      => (int)$si['quantity'],
                        'rate'          => (float)$si['rate'],
                        'total'         => (float)$si['total'],
                        'type'          => $orderTypeLower,
                        'remarks'       => $si['remarks'] ?? '',
                        'size'          => $si['size'] ?? null,
                    ]);
                }

                $createdSplit = true;
            }

            /**
             * 4E) Documents: invoice + packing slip
             */
            $invoiceCtrl = new InvoiceController();

            // For current order
            $invoiceCtrl->generateorderInvoice($order->id, [
                'is_edited'      => true,
                'is_merged'      => $isMerged,
                'merged_orders'  => $mergedOrderCodes,
                'is_split'       => false,
                'old_order_id'   => '',
            ]);
            $invoiceCtrl->generatePackingSlip($order->id, true);

            // For new split order (if created)
            if ($createdSplit && $newSplitOrder) {
                $invoiceCtrl->generateorderInvoice($newSplitOrder->id, [
                    'is_edited'    => true,
                    'is_split'     => true,
                    'old_order_id' => $splitFromCode,
                ]);
                $invoiceCtrl->generatePackingSlip($newSplitOrder->id, true);
            }

            DB::commit();

            return response()->json([
                'message'   => 'Order updated successfully!',
                'order'     => $order->fresh('order_items'),
                'new_order' => $newSplitOrder ?? null,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to edit order', [
                'order_id' => $id,
                'user_id'  => $authUserId,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to update order.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


    // public function complete_order(Request $request, $id)
    // {
    //     // Validate incoming request data
    //     $request->validate([
    //         'order_id' => 'required|string',
    //         'user_id' => 'required|integer'
    //     ]);

    //     // Find the order by its ID
    //     $order = OrderModel::find($id);

    //     if (!$order) {
    //         return response()->json([
    //             'message' => 'Order not found!'
    //         ], 404);
    //     }

    //     // Check if the order belongs to the provided user_id
    //     if ($order->user_id !== $request->input('user_id')) {
    //         return response()->json([
    //             'message' => 'Unauthorized action. This order does not belong to the specified user.'
    //         ], 403);
    //     }

    //     // Update the status of the order to 'completed'
    //     $order->status = 'completed';
    //     $order->save();

    //     return response()->json([
    //         'message' => 'Order status updated to completed successfully!',
    //         'order' => $order
    //     ], 200);
    // }

   
    public function complete_order(Request $request, $id)
    {
        // Base validation (unchanged + conditional for 226 below)
        $request->validate([
            'order_id' => 'required|string',   // human order id of the QUOTE (e.g., SS/ORB/81/24-25)
            'user_id'  => 'required|integer',  // who owns the quote (226 for quotation user)
        ]);

        $quote = OrderModel::find($id);
        if (!$quote) {
            return response()->json(['message' => 'Order not found!'], 404);
        }

        // Ensure the quote belongs to the provided user
        if ($quote->user_id !== (int) $request->input('user_id')) {
            return response()->json([
                'message' => 'Unauthorized action. This order does not belong to the specified user.'
            ], 403);
        }

        // (Optional but recommended) sanity: make sure the provided order_id matches the quote we found
        if ($request->input('order_id') !== $quote->order_id) {
            return response()->json(['message' => 'Provided order_id does not match the quote.'], 422);
        }

        // === SPECIAL FLOW for Quotation user (id: 226) ===
        if ((int) $request->input('user_id') === 226) {

            // Require name & mobile for 226
            $request->validate([
                'name'   => 'required|string|max:191',
                'mobile' => 'required|string|max:25',
            ]);

            // Normalize mobile lightly (keep your preferred format; this adds +91 if 10 digits)
            $name   = trim((string) $request->input('name'));
            $mobile = preg_replace('/\s+/', '', (string) $request->input('mobile'));
            if (preg_match('/^\d{10}$/', $mobile)) {
                $mobile = '+91' . $mobile;
            }

            // Load quote items; cannot convert empty quote
            $quoteItems = OrderItemsModel::where('order_id', $quote->id)->get();
            if ($quoteItems->isEmpty()) {
                return response()->json([
                    'message' => 'Quotation has no items to convert.'
                ], 422);
            }

            // Choose counter for the **new live order** (NOT the quotation counter)
            $newOrderCounterName = ($quote->type === 'gst') ? 'order_gst' : 'order_basic';

            [$clientUser, $newOrder] = DB::transaction(function () use ($name, $mobile, $quote, $quoteItems, $newOrderCounterName) {

                // 1) Find or create a **client user** by mobile
                /** @var \App\Models\User|null $clientUser */
                $clientUser = User::where('mobile', $mobile)->first();

                if (!$clientUser) {
                    // Ensure username uniqueness if you use username-based login
                    $proposedUsername = $mobile;
                    if (User::where('mobile', $proposedUsername)->exists()) {
                        $proposedUsername = $mobile . '_' . str()->random(4);
                    }

                    $clientUser = User::create([
                        'name'     => $name,
                        'mobile'   => $mobile,
                        'password' => Hash::make(str()->random(12)), // random password (can be reset later)
                        'role'     => 'user',
                        'is_verified'     => 1,
                    ]);
                } else {
                    // Optionally update name if missing/mismatched
                    if (!$clientUser->name || $clientUser->name !== $name) {
                        $clientUser->name = $name;
                        $clientUser->save();
                    }
                }

                // 2) Reserve a new order_id by locking the counter
                /** @var \App\Models\CounterModel $counter */
                $counter = CounterModel::where('name', $newOrderCounterName)->lockForUpdate()->first();
                if (!$counter) {
                    throw new \RuntimeException("Counter '{$newOrderCounterName}' not found.");
                }
                $newOrderHumanId = $counter->prefix . $counter->counter . $counter->postfix;

                // 3) Create the **new live order** under the client user
                /** @var \App\Models\OrderModel $newOrder */
                $newOrder = OrderModel::create([
                    'user_id'    => $clientUser->id,
                    'order_id'   => $newOrderHumanId,
                    'order_date' => Carbon::now(),
                    'amount'     => $quote->amount,
                    'type'       => $quote->type === 'gst' ? 'gst' : 'basic',
                    'remarks'    => trim(($quote->remarks ? $quote->remarks . ' | ' : '') . 'Converted from quotation: ' . $quote->order_id),
                    'status'     => 'pending',   // your default live status
                    // carry forward person-of-contact to the order if columns exist
                    'name'       => $name,
                    'mobile'     => $mobile,
                ]);

                // 4) Copy items from quote → new order
                $bulk = [];
                foreach ($quoteItems as $item) {
                    $bulk[] = [
                        'order_id'     => $newOrder->id,
                        'product_code' => $item->product_code,
                        'product_name' => $item->product_name,
                        'rate'         => $item->rate,
                        'quantity'     => $item->quantity,
                        'total'        => $item->total,
                        'size'         => $item->size,
                        'type'         => $item->type,
                        'remarks'      => $item->remarks,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ];
                }
                OrderItemsModel::insert($bulk);

                // 5) Increment counter (atomic within transaction)
                $counter->increment('counter');

                // 6) Mark the original quote as completed
                $quote->status = 'completed';
                $quote->save();

                return [$clientUser, $newOrder];
            });

            // Optional: trigger invoice for the new order here, if desired
            // app(\App\Http\Controllers\InvoiceController::class)->generateorderInvoice($newOrder->id, ['is_edited' => false]);

            return response()->json([
                'message' => 'Quotation completed: client user created/linked and new order placed.',
                'quotation' => [
                    'id'       => $quote->id,
                    'order_id' => $quote->order_id,
                    'status'   => $quote->status,
                ],
                'client_user' => [
                    'id'       => $clientUser->id,
                    'name'     => $clientUser->name,
                    'mobile'   => $clientUser->mobile,
                ],
                'new_order' => [
                    'id'       => $newOrder->id,
                    'order_id' => $newOrder->order_id,
                    'type'     => $newOrder->type,
                    'amount'   => $newOrder->amount,
                    'status'   => $newOrder->status,
                ],
            ], 200);
        }

        // === DEFAULT BASE FLOW (unchanged for non-226) ===
        $quote->status = 'completed';
        $quote->save();

        return response()->json([
            'message' => 'Order status updated to completed successfully!',
            'order'   => $quote
        ], 200);
    }



    public function complete_order_stock(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $order = OrderModel::find($id);
            if (!$order) {
                return response()->json(['message' => 'Order not found!'], 404);
            }

            // Log the raw request
            LogsModel::create([
                'function'   => 'complete_order_stock',
                'request'    => json_encode([
                    'params'  => $request->all(),
                    'order_id'=> $id,
                    'user_id' => Auth::id()
                ]),
                'created_at' => now(),
            ]);

            // Mark completed
            $order->status = 'completed';
            $order->save();

            // Atomic counter fetcher
            $nextOrderId = function () {
                $counter = CounterModel::where('name', 'stock_order')->lockForUpdate()->firstOrFail();
                $oid = $counter->prefix . str_pad($counter->counter, 5, '0', STR_PAD_LEFT) . $counter->postfix;
                $counter->increment('counter');
                return $oid;
            };

            // Helper: match "DIRECT" godown variants safely
            $isDirect = function (?string $name): bool {
                if (!$name) return false;
                $n = mb_strtoupper(trim($name));
                return $n === 'DIRECT DISPATCH' || $n === 'DIRECT GODOWN' || str_starts_with($n, 'DIRECT');
            };

            // Optionally resolve DIRECT godown id (if you maintain one)
            $directGodownId = null;
            if (class_exists(\App\Models\GodownModel::class)) {
                $directGodownId = \App\Models\GodownModel::whereIn('name', ['DIRECT DISPATCH','DIRECT GODOWN'])->value('id');
            }

            // ===== Aggregation =====
            // OUT: all allocations, grouped by product|size|godown
            // IN : only DIRECT allocations, grouped by product|size  (single DD line per SKU/size)
            $outAgg = []; // key: product|size|godown_id
            $inDDAgg = []; // key: product|size

            $items = $request->input(); // expected: [ { product_code, product_name, size?, allocations:[{godown_id, godown_name, qty}] }, ... ]
            foreach ($items as $item) {
                $pcode = $item['product_code'];
                $pname = $item['product_name'];
                $size  = $item['size'] ?? null;

                foreach ($item['allocations'] as $a) {
                    $qty   = (float) ($a['qty'] ?? 0);
                    if ($qty <= 0) continue;
                    $gid   = $a['godown_id'] ?? null;
                    $gname = $a['godown_name'] ?? '';

                    // OUT (always, split by godown)
                    $keyOut = $pcode.'|'.($size ?? '').'|'.($gid ?? '0');
                    if (!isset($outAgg[$keyOut])) {
                        $outAgg[$keyOut] = [
                            'product_code' => $pcode,
                            'product_name' => $pname,
                            'size'         => $size,
                            'godown_id'    => $gid,
                            'quantity'     => 0,
                        ];
                    }
                    $outAgg[$keyOut]['quantity'] += $qty;

                    // IN (only DIRECT xfers)
                    if ($isDirect($gname)) {
                        $keyIn = $pcode.'|'.($size ?? '');
                        if (!isset($inDDAgg[$keyIn])) {
                            $inDDAgg[$keyIn] = [
                                'product_code' => $pcode,
                                'product_name' => $pname,
                                'size'         => $size,
                                'godown_id'    => $directGodownId ?? $gid, // prefer canonical DIRECT id
                                'quantity'     => 0,
                            ];
                        }
                        $inDDAgg[$keyIn]['quantity'] += $qty;
                    }
                }
            }

            // ===== Create OUT order (all godowns) =====
            $stockOrderOut = null;
            if (!empty($outAgg)) {
                $outId = $nextOrderId();
                $stockOrderOut = StockOrdersModel::create([
                    'order_id'   => $outId,
                    'user_id'    => Auth::id(),
                    'order_date' => now(),
                    'type'       => 'OUT',
                    't_order_id' => $id,
                    'pdf'        => null,
                    'remarks'    => "{$order->order_id}",
                ]);

                foreach ($outAgg as $row) {
                    StockOrderItemsModel::create([
                        'stock_order_id' => $stockOrderOut->id,
                        'product_code'   => $row['product_code'],
                        'product_name'   => $row['product_name'],
                        'godown_id'      => $row['godown_id'],
                        'quantity'       => $row['quantity'],
                        'type'           => 'OUT',
                        'size'           => $row['size'],
                    ]);
                }
            }

            // ===== Create IN order (DIRECT only) =====
            $stockOrderIn = null;
            if (!empty($inDDAgg)) {
                $inId = $nextOrderId();
                $stockOrderIn = StockOrdersModel::create([
                    'order_id'   => $inId,
                    'user_id'    => Auth::id(),
                    'order_date' => now(),
                    'type'       => 'IN',
                    't_order_id' => $id,
                    'pdf'        => null,
                    'remarks'    => "{$order->order_id}",
                ]);

                foreach ($inDDAgg as $row) {
                    StockOrderItemsModel::create([
                        'stock_order_id' => $stockOrderIn->id,
                        'product_code'   => $row['product_code'],
                        'product_name'   => $row['product_name'],
                        'godown_id'      => $row['godown_id'], // DIRECT godown id
                        'quantity'       => $row['quantity'],
                        'type'           => 'IN',
                        'size'           => $row['size'],
                    ]);
                }
            }

            // Generate PDFs after items are created
            $invoice = new InvoiceControllerZP();
            if ($stockOrderOut) {
                $stockOrderOut->pdf = $invoice->generatestockorderInvoice($stockOrderOut->id);
                $stockOrderOut->save();
            }
            if ($stockOrderIn) {
                $stockOrderIn->pdf = $invoice->generatestockorderInvoice($stockOrderIn->id);
                $stockOrderIn->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Done. OUT has all items split by godown; IN has only DIRECT quantities.',
                'order'   => $order,
                'stock_orders' => [
                    'out' => $stockOrderOut ? $stockOrderOut->order_id : null,
                    'in'  => $stockOrderIn ? $stockOrderIn->order_id : null,
                ],
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('complete_order_stock failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to complete order stock.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function cancel_order(Request $request, $id)
    {
        // Validate incoming request data
        $request->validate([
            'order_id' => 'required|string',
            'user_id' => 'required|integer'
        ]);

        // Find the order by its ID
        $order = OrderModel::find($id);

        if (!$order) {
            return response()->json([
                'message' => 'Order not found!'
            ], 404);
        }

        // Check if the order belongs to the provided user_id
        if ($order->user_id !== $request->input('user_id')) {
            return response()->json([
                'message' => 'Unauthorized action. This order does not belong to the specified user.'
            ], 403);
        }

        // Update the status of the order to 'completed'
        $order->status = 'cancelled';
        $order->save();

        $user = User::find($order->user_id);
        $mobileNumbers = User::where('role', 'admin')->pluck('mobile')->toArray();

        $whatsAppUtility = new sendWhatsAppUtility();

        $templateParams = [
            'name' => 'ace_order_cancelled', // Replace with your WhatsApp template name
            'language' => ['code' => 'en'],
            'components' => [[
                    'type' => 'body',
                    'parameters' => [
                        [
                            'type' => 'text',
                            'text' => $user->name,
                        ],
                        [
                            'type' => 'text',
                            'text' => $order->order_id,
                        ],
                        [
                            'type' => 'text',
                            'text' => Carbon::parse($order->order_date)->format('d-m-Y'),
                        ],
                        [
                            'type' => 'text',
                            'text' => $order->amount,
                        ],
                    ],
                ]
            ],
        ];

        foreach ($mobileNumbers as $mobileNumber) 
        {
            if($mobileNumber == '+918961043773' || true)
            {
                // Send message for each number
                $response = $whatsAppUtility->sendWhatsApp($mobileNumber, $templateParams, '', 'Order Cancel Notification');
            }
        }

        $response = $whatsAppUtility->sendWhatsApp($user->mobile, $templateParams, '', 'Order Cancel Notification');

        return response()->json([
            'message' => 'Order has been cancelled successfully!',
            'order' => $order
        ], 200);
    }

    public function stock_cart_update(Request $request, $id)
    {
        // Fetch the stock cart item for the authenticated user
        $stockCartItem = StockCartModel::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        // Return response using ternary operator if the item is not found
        return !$stockCartItem
            ? response()->json(['message' => 'Stock cart item not found.', 'count' => 0], 404)
            : (function () use ($request, $stockCartItem) {
                // Validate the request
                $validated = $request->validate([
                    'product_code' => 'required|string|exists:t_products,product_code',
                    'product_name' => 'required|string|exists:t_products,product_name',
                    'quantity' => 'required|integer|min:1',
                    'godown_id' => 'required|integer|exists:t_godown,id',
                    'type' => 'required|in:IN,OUT',
                    'size' => 'nullable',
                ]);

                // Update the stock cart item
                $stockCartItem->update([
                    'product_code' => $validated['product_code'],
                    'product_name' => $validated['product_name'],
                    'quantity' => $validated['quantity'],
                    'godown_id' => $validated['godown_id'],
                    'type' => $validated['type'],
                    'size' => $validated['size'] ?? null,
                ]);

                // Return the success response
                return response()->json([
                    'message' => 'Stock cart item updated successfully.',
                    'data' => $stockCartItem->makeHidden(['id', 'updated_at', 'created_at']),
                ], 200);
            })();
    }

    public function updateStockOrder(Request $request, $orderId)
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'remarks' => 'nullable|string|max:255',
                'items' => 'required|array',
                'items.*.product_code' => 'required|string|exists:t_products,product_code',
                'items.*.product_name' => 'required|string|exists:t_products,product_name',
                'items.*.godown_id' => 'required|integer|exists:t_godown,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.type' => 'required|in:IN,OUT',
                'items.*.size' => 'nullable',
            ]);

            // Fetch the stock order by order_id
            $stockOrder = StockOrdersModel::where('id', $orderId)->first();

            if (!$stockOrder) {
                return response()->json([
                    'message' => 'Stock order not found.',
                    'status' => 'false',
                ], 404);
            }

            // Fetch the current user's ID
            $userId = Auth::id();

            // Ensure the stock order belongs to the authenticated user
            if ($stockOrder->user_id !== $userId) {
                return response()->json([
                    'message' => 'Unauthorized to update this stock order.',
                    'status' => 'false',
                ], 403);
            }

            // Update the stock order
            $stockOrder->update([
                'remarks' => $validated['remarks'] ?? $stockOrder->remarks,
            ]);

            // Remove existing stock order items
            StockOrderItemsModel::where('stock_order_id', $stockOrder->id)->delete();

            // Add new items to the stock order
            $items = $validated['items'];
            foreach ($items as $item) {
                StockOrderItemsModel::create([
                    'stock_order_id' => $stockOrder->id,
                    'product_code' => $item['product_code'],
                    'product_name' => $item['product_name'],
                    'godown_id' => $item['godown_id'],
                    'quantity' => $item['quantity'],
                    'type' => $item['type'],
                    'size' => $item['size'] ?? null,
                ]);
            }

            $generate_stock_order_invoice = new InvoiceControllerZP();
            $stockOrder->pdf = $generate_stock_order_invoice->generatestockorderInvoice($stockOrder->id, true);

            return response()->json([
                'message' => 'Stock order updated successfully.',
                'data' => [
                    'order_id' => $stockOrder->order_id,
                    'order_date' => $stockOrder->order_date,
                    'type' => $stockOrder->type,
                    'godown' => $stockOrder->godown_id,
                    'remarks' => $stockOrder->remarks,
                    'items' => $items,
                ],
                'status' => 'true',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while updating the stock order.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


        /**
     * UPDATE (column-wise)
     * Only updates fields that are present in the request.
     * Validates that user_id/product_code exist if provided.
     * Enforces uniqueness of (user_id, product_code).
     */
    public function updateSpecialRate(Request $request, $id)
    {
        try {
            // Find record
            $special = SpecialRateModel::with(['user:id,name,mobile,city,type'])->find($id);
            if (!$special) {
                return response()->json([
                    'success' => false,
                    'message' => 'Special rate record not found.'
                ], 404);
            }

            // Column-wise validation rules (only rate is required)
            $validated = $request->validate([
                'rate' => 'required|numeric|min:0',  // Ensure rate is provided and valid
            ]);

            // Only update rate if provided
            $payload = [];

            // If rate provided, update it
            if ($request->has('rate')) {
                $payload['rate'] = (float)$validated['rate'];
            }

            // If nothing to update
            if (empty($payload)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No changes provided.',
                    'data'    => $special
                ], 200);
            }

            // Update the special rate record with the new rate
            $special->fill($payload)->save();

            // Eager refresh (with user block for response shape)
            $special->load('user:id,name,mobile,city,type');

            // Prepare the response shape
            $resp = [
                'user_id' => (string)($special->user->id ?? $special->user_id),
                'name'    => (string)($special->user->name ?? ''),
                'mobile'  => (string)($special->user->mobile ?? ''),
                'city'    => (string)($special->user->city ?? ''),
                'type'    => (string)($special->user->type ?? ''),
                'special_rate' => [[
                    'id'            => (string)$special->id,
                    'product_code'  => (string)$special->product_code,
                    'rate'          => (string)$special->rate,
                    'original_rate' => '0', // Placeholder for original_rate
                ]],
            ];

            return response()->json([
                'success' => true,
                'message' => 'Special rate updated successfully.',
                'data'    => $resp,
            ], 200);

        } catch (QueryException $qe) {
            // Handle DB-level unique key errors gracefully
            Log::error('SpecialRate Update DB Error: '.$qe->getMessage());
            if ((int)$qe->getCode() === 23000) { // integrity constraint violation
                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate entry for (user_id, product_code).',
                    'error'   => $qe->getMessage()
                ], 409);
            }
            return response()->json([
                'success' => false,
                'message' => 'Database error while updating special rate.',
                'error'   => $qe->getMessage()
            ], 500);
        } catch (\Throwable $e) {
            Log::error('SpecialRate Update Error: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating special rate.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    /**
     * UPDATE (column-wise).
     * Only updates fields that are present in the request.
     * Disallows changing job_id (since it’s generated).
     */
    public function updateJobCard(Request $request, $id)
    {
        try {
            $job = JobCardModel::find($id);
            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job card not found.',
                ], 404);
            }

            // Validate only provided fields
            $validated = $request->validate([
                'client_name'         => ['required', 'string', 'max:191'],
                'mobile'              => ['required', 'string', 'max:20'],
                'warranty'            => ['required', Rule::in(['in_warranty','outside_warranty'])],
                'serial_no'           => ['sometimes', 'nullable', 'string', 'max:100'],
                'model_no'            => ['sometimes', 'nullable', 'string', 'max:100'],
                'problem_description' => ['sometimes', 'nullable', 'string'],
                'assigned_to'         => ['sometimes', 'nullable', 'string', 'max:191'], // varchar
                // 'job_id' is intentionally NOT allowed here
            ]);

            // Build payload column-wise
            $payload = [];
            foreach (['client_name','mobile','warranty','serial_no','model_no','problem_description','assigned_to'] as $col) {
                if ($request->has($col)) {
                    $payload[$col] = $validated[$col] ?? null;
                }
            }

            if (empty($payload)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No changes provided.',
                    'data'    => $job,
                ], 200);
            }

            $job->fill($payload)->save();

            // Fresh copy for response
            $job->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Job card updated successfully.',
                'data'    => $job,
            ], 200);

        } catch (QueryException $qe) {
            Log::error('JobCard update DB error: '.$qe->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Database error while updating job card.',
                'error'   => $qe->getMessage(),
            ], 500);
        } catch (\Throwable $e) {
            Log::error('JobCard update error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating job card.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
