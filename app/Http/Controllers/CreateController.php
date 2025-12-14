<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductModel;
use App\Models\User;
use App\Models\OrderModel;
use App\Models\OrderItemsModel;
use App\Models\CartModel;
use App\Models\CounterModel;
use App\Models\InvoiceModel;
use App\Models\InvoiceItemsModel;
use App\Models\StockCartModel;
use App\Models\StockOrdersModel;
use App\Models\StockOrderItemsModel;
use App\Models\SpecialRateModel;
use App\Models\JobCardModel;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceControllerZP;
use Illuminate\Support\Facades\Auth;
use App\Utils\sendWhatsAppUtility;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;
use Hash;

class CreateController extends Controller
{
    public function user(Request $request)
    {
        $request->validate([
            // 'email' => 'required|unique:users,email',
            'mobile' => ['required', 'string', 'size:13', 'unique:users'],
            'name' => 'required',
            'password' => 'required',
            // 'role' => 'required',
            // 'category_discount' => 'required',
            // 'address_line_1' => 'required',
            // 'city' => 'required',
            // 'pincode' => 'required',
        ]);
        
        $create_user = User::create([
            'name' => $request->input('name'),
            'password' => bcrypt($request->input('password')),
            'email' => strtolower($request->input('email')) ?? null,
            'mobile' => $request->input('mobile'),
            'role' => 'user',
            'address_line_1' => $request->input('address_line_1') ?? null,
            'address_line_2' => $request->input('address_line_2') ?? null,
            'city' => $request->input('city') ?? null,
            'pincode' => $request->input('pincode') ?? null,
            'gstin' => $request->input('gstin') ?? null,
            'state' => $request->input('state') ?? null,
            'country' => $request->input('country') ?? null,
            // 'category_discount' => $request->input('category_discount'),
        ]);


        if (isset($create_user)) {

            unset($create_user['id'], $create_user['created_at'], $create_user['updated_at']);

            $mobileNumbers = User::where('role', 'admin')->pluck('mobile')->toArray();

            $templateParams = [
                'name' => 'ace_new_user_registered', // Replace with your WhatsApp template name
                'language' => ['code' => 'en'],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $create_user->name,
                            ],
                            [
                                'type' => 'text',
                                'text' => $create_user->mobile,
                            ],
                            [
                                'type' => 'text',
                                'text' => $create_user->state,
                            ],
                        ],
                    ]
                ],
            ];
            
            $whatsAppUtility = new sendWhatsAppUtility();
            
            foreach ($mobileNumbers as $mobileNumber)
            {
                // Send message for each number

                $response = $whatsAppUtility->sendWhatsApp($mobileNumber, $templateParams, '', 'User Register');

                // Decode the response into an array
                $responseArray = json_decode($response, true);

                // Check if the response has an error or was successful
                if (isset($responseArray['error'])) 
                {
                    //echo "Failed to send message to Whatsapp!";
                } 
                
            }

            return response()->json([
                'message' => 'User registered successfully!',
                'data' => $create_user
            ], 201);
        }

        else {
            return response()->json([
                'message' => 'Failed to create successfully!',
            ], 500);
        }    
    }

    public function login(Request $request, $otp = null)
    {
        $mobile = $request->mobile;
        if ($otp) {
            $request->validate([
                'mobile' => ['required', 'string'],
            ]);

            

            if (strlen($mobile) === 15) {
                // Remove 2nd and 3rd characters
                $mobile = substr($mobile, 0, 1) . substr($mobile, 3);
            }

            $otpRecord = User::select('otp', 'expires_at')
                ->where('mobile', $mobile)
                ->first();

            if ($otpRecord) {
                // Validate OTP and expiry
                if (!$otpRecord || $otpRecord->otp != $otp) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid Credentials.',
                    ], 200);
                }

                if ($otpRecord->expires_at < now()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'OTP has expired.',
                    ], 200);
                } else {

                    // Retrieve the user
                    $user = User::where('mobile', $mobile)->first();

                    // Check if user is verified
                    if ($user->is_verified == '0') {                        
                        $whatsAppUtility = new sendWhatsAppUtility();

                        $templateParams = [
                            'name' => 'ace_login_attempt', // Replace with your WhatsApp template name
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
                                            'text' =>  substr($user->mobile, -10),
                                        ]
                                    ],
                                ]
                            ],
                        ];

                        $mobileNumbers = User::where('role', 'admin')->pluck('mobile')->toArray();
                        foreach ($mobileNumbers as $mobileNumber) 
                        {
                            if($mobileNumber == '+918961043773' || true)
                            {
                                // Send message for each number
                                $response = $whatsAppUtility->sendWhatsApp($mobileNumber, $templateParams, '', 'Admin Order Invoice');

                                // Check if the response has an error or was successful
                                if (isset($responseArray['error'])) 
                                {
                                    echo "Failed to send order to Whatsapp!";
                                }
                            }
                        }
                        
                        return response()->json([
                            'success' => false,
                            'message' => 'Account not verified, you will receive a notification once your account is verified.',
                        ], 200);
                    }

                    // Remove OTP record after successful validation
                    User::where('mobile', $mobile)->update(['otp' => null, 'expires_at' => null]);
                    

                    // Generate a Sanctum token
                    $token = $user->createToken('API TOKEN')->plainTextToken;
                    $stock = false;

                    if($user->role == 'admin' || $user->mobile == '+917981553591' || $user->mobile == '+919951263652'){
                        $stock = true;
                    }

                    return response()->json([
                        'success' => true,
                        'data' => [
                            'token' => $token,
                            'name' => $user->name,
                            'role' => $user->role,
                            'can_edit' => $user->can_edit,
                            'type' => $user->type,
                            'stock' => $stock,
                            'manager_mobile_number' => "+917506691380",
                        ],
                        'message' => 'User login successfully.',
                    ], 200);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Credentials.',
                ], 200);
            }
        } else {
            $request->validate([
                'mobile' => 'required',
                'password' => 'required',
            ]);

            if (Auth::attempt(['mobile' => $mobile, 'password' => $request->password])) {
                $user = Auth::user();

                // Check if user is verified
                if ($user->is_verified == '0') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Account not verified, you will receive a notification once your account is verified.',
                    ], 200);
                }

                // Generate a Sanctum token
                $token = $user->createToken('API TOKEN')->plainTextToken;
                $stock = false;

                if($user->role == 'admin' || $user->mobile == '+917981553591'){
                    $stock = true;
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'token' => $token,
                        'name' => $user->name,
                        'role' => $user->role,
                        'can_edit' => $user->can_edit,
                        'type' => $user->type,
                        'stock' => $stock,
                        'manager_mobile_number' => "+917506691380",
                    ],
                    'message' => 'User login successfully.',
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Credentials.',
                ], 200);
            }
        }
    }

    public function logout(Request $request)
    {
        // Check if the user is authenticated
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'No user is authenticated.',
            ], 401); // 401 Unauthorized
        }

        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ], 204);
    }

    public function webLogout(Request $request)
    {
        // Log the user out of the session
        Auth::logout();

        // Invalidate the user's session
        $request->session()->invalidate();

        // Regenerate the session token to prevent CSRF attacks
        $request->session()->regenerateToken();

        return redirect('/login')->with('success', 'Logged out successfully.');
    }
    
    public function product(Request $request)
    {
        $request->validate([
            // 'sku' => 'required|unique:t_products,sku',
            'product_code' => 'required|unique:t_products,product_code',
            'product_name' => 'required',
            'product_image'=> 'required',
            'basic'=>'required',
            'gst'=>'required',
            'size'=>'required',
            // 'mark_up'=>'required',
        ]);

        if($request->hasFile('product_image'))
        {
            $file = $request->file('product_image');
            // $filename = time().'_'. $file->getClientOriginalName();
            $filename = $file->getClientOriginalName();
            $path = $file->storeAs('uploads/products', $filename, 'public');
            $fileUrl = ('storage/uploads/products' . $filename); 
            $get_file_name = $filename;


            $create_product = ProductModel::create([
                // 'sku' => $request->input('sku'),
                'product_code' => $request->input('product_code'),
                'product_name' => $request->input('product_name'),
                'category' => $request->input('category'),
                'sub_category' => $request->input('sub_category'),
                'product_image' => $fileUrl,
                'basic' => $request->input('basic'),
                'gst' => $request->input('gst'),
                'size' => $request->input('size'),
                // 'mark_up' => $request->input('mark_up'),
            ]);
        }


        if (isset($create_product)) {
            return response()->json([
                'message' => 'Product created successfully!',
                'data' => $create_product
            ], 201);
        }

        else {
            return response()->json([
                'message' => 'Failed created successfully!',
                'data' => $create_order
            ], 400);
        }    
    }

    public function orders(Request $request)
    {
        $get_user = Auth::User();

        if($get_user->role == 'user') {
            $userId = $get_user->id;
        }

        else 
        {
            $request->validate([
                // 'client_id' => 'required',
                'user_id' => 'required',
            ]);
            $userId = $request->input('user_id');
        }

        // --- ADD: Special handling for Quotation user (id: 226) ---
        $isQuotationUser = ($userId == 226);

        // For Quotation user, name & mobile are compulsory
        if ($isQuotationUser) {
            $request->validate([
                'name'   => 'required|string|max:191',
                'mobile' => 'required|string|max:20', // keep string to allow +91, spaces, etc.
            ]);
        }

        $customerName   = $isQuotationUser ? $request->input('name')   : null;
        $customerMobile = $isQuotationUser ? $request->input('mobile') : null;

        $current_user = User::select('type', 'purchase_lock')->where('id', $userId)->first();
        $user_type = $current_user->type;

        // if($userId == 181)
        // {
            if($current_user->purchase_lock == 1)
            {
                return response()->json([
                    'message' => 'Your outstanding payment exceeds the allocated purchase limit. Kindly clear the previous dues to continue placing your order.',
                    'data' => 'locked'
                ], 200);
            }

            $get_product = CartModel::select('amount', 'quantity', 'product_code', 'product_name', 'rate', 'size', 'type', 'remarks')
                                        ->where('user_id', $userId)
                                        ->get();

            $amount_total = 0;
            foreach ($get_product as $product) 
            {
                $amount_total += (($product->rate) * ($product->quantity));
            }

            if($amount_total > 500000000)
            {
                $message = "Apologies, but you have exceeded your purchase limit of ₹50,000. Kindly remove a few items from your cart to bring the total below the purchase limit and proceed with your order.";
                return response()->json([
                    'message' => $message,
                    'data' => 'locked'
                ], 200);
            }
        // }

        if($user_type == 'zeroprice')
        {

            $get_product = CartModel::select('amount', 'quantity', 'product_code', 'product_name', 'rate', 'size', 'type', 'remarks')
                                        ->where('user_id', $userId)
                                        ->get();

            // $get_counter = CounterModel::select('prefix', 'counter', 'postfix')->where('name', 'order_zeroprice')->get();
            $counterName = $isQuotationUser ? 'quotation' : 'order_zeroprice';
            $get_counter = CounterModel::select('prefix', 'counter', 'postfix')
                ->where('name', $counterName)
                ->get();


            if ($get_counter) 
            {
                $get_order_id = $get_counter[0]->prefix.$get_counter[0]->counter.$get_counter[0]->postfix;
                $create_order = null;
                // for `basic` product
                if ((count($get_product)) > 0) 
                {
                    $amount_total = 0;
                    foreach ($get_product as $product) 
                    {
                        $amount_total += (($product->rate) * ($product->quantity));
                    }
                    
                    $create_order = OrderModel::create([
                        'user_id' => $userId,
                        'order_id' => $get_order_id,
                        'order_date' => Carbon::now(),
                        'amount' => $amount_total,
                        'type' => 'basic',
                        'remarks' => $request->input('remarks'),
                        // ✅ add this line
                        'status' => ($userId == 489) ? 'completed' : 'pending',
                        // NEW fields for Quotation user
                        'name'       => $customerName,
                        'mobile'     => $customerMobile,
                    ]);
                    //order_table_id

                    foreach ($get_product as $product) 
                    {
                        // save every item in order_items with order_table_id
                        $create_order_items = OrderItemsModel::create([
                            'order_id' => $create_order->id,
                            'product_code' => $product->product_code,
                            'product_name' => $product->product_name,
                            'rate' => $product->rate,
                            'quantity' => $product->quantity,
                            'total' => $product->rate * $product->quantity,
                            'size' => $product->size,
                            'type' => $product->type,
                            'remarks' => $product->remarks,
                        ]);
                    }

                     
                }

                // if ($create_order != null) {
                //     $update_cart = CounterModel::where('name', 'order_zeroprice')
                //     ->update([
                //         'counter' => (($get_counter[0]->counter)+1),
                //     ]);
                // }

                if ($create_order != null) {
                    CounterModel::where('name', $counterName)->increment('counter');
                }            

                $data = [];

                // Check if data_basic exists and is not null, then add it to the array
                if (!empty($create_order)) {
                    $data[] = $create_order;
                }

                // Remove items from the cart for the user
                $get_remove_items = CartModel::where('user_id', $userId)->delete();

                if ($create_order !== null ) {
                    $generate_order_zp = new InvoiceControllerZP();
    
                    // Iterate through the $data array and add the corresponding invoices
                    foreach ($data as &$order) {
                        // Unset unwanted fields
    
                        $order->pdf = $generate_order_zp->generateorderInvoiceZP($create_order->id);
                        unset($order->updated_at, $order->created_at, $order->id);
    
                    }
    
                    return response()->json([
                        'message' => 'Order created and Invoice generated successfully!',
                        'data' => $data
                    ], 201);
                }
    
    
                else {
                    return response()->json([
                        'message' => 'Sorry, failed to create order!',
                        'data' => 'Error!'
                    ], 400);
                }  

            }
        }else{

            $create_order_basic = null;
            $create_order_gst = null;

            $get_basic_product = CartModel::select('amount', 'quantity', 'product_code', 'product_name', 'rate', 'size', 'type', 'remarks')
                                        ->where('user_id', $userId)
                                        ->where('type', 'basic')
                                        ->get();

            // $get_counter_basic = CounterModel::select('prefix', 'counter', 'postfix')->where('name', 'order_basic')->get();
            $counterNameBasic = $isQuotationUser ? 'quotation' : 'order_basic';
            $get_counter_basic = CounterModel::select('prefix', 'counter', 'postfix')
                ->where('name', $counterNameBasic)
                ->get();

            if ($get_counter_basic) 
            {
                $get_order_id = $get_counter_basic[0]->prefix.$get_counter_basic[0]->counter.$get_counter_basic[0]->postfix;
        
                // for `basic` product
                if ((count($get_basic_product)) > 0) 
                {
                    $basic_amount_total = 0;
                    foreach ($get_basic_product as $basic_product) 
                    {
                        $basic_amount_total += (($basic_product->rate) * ($basic_product->quantity));
                    }
                    
                    $create_order_basic = OrderModel::create([
                        'user_id' => $userId,
                        'order_id' => $get_order_id,
                        'order_date' => Carbon::now(),
                        'amount' => $basic_amount_total,
                        'type' => 'basic',
                        'remarks' => $request->input('remarks'),
                        // ✅ add this line
                        'status' => ($userId == 489) ? 'completed' : 'pending',
                        // NEW fields for Quotation user
                        'name'       => $customerName,
                        'mobile'     => $customerMobile,
                    ]);
                    //order_table_id

                    foreach ($get_basic_product as $basic_product) 
                    {
                        // save every item in order_items with order_table_id
                        $create_order_items = OrderItemsModel::create([
                            'order_id' => $create_order_basic->id,
                            'product_code' => $basic_product->product_code,
                            'product_name' => $basic_product->product_name,
                            'rate' => $basic_product->rate,
                            'quantity' => $basic_product->quantity,
                            'total' => $basic_product->rate * $basic_product->quantity,
                            'size' => $basic_product->size,
                            'type' => $basic_product->type,
                            'remarks' => $basic_product->remarks,
                        ]);
                    }
                }

            }

            $get_gst_product = CartModel::select('amount', 'quantity', 'product_code', 'product_name', 'rate', 'size', 'type', 'remarks')
                                        ->where('user_id', $userId)
                                        ->where('type', 'gst')
                                        ->get();

            // $get_counter_gst = CounterModel::select('prefix', 'counter', 'postfix')->where('name', 'order_gst')->get();
            $counterNameGst = $isQuotationUser ? 'quotation' : 'order_gst';
            $get_counter_gst = CounterModel::select('prefix', 'counter', 'postfix')
                ->where('name', $counterNameGst)
                ->get();

                
            if ($get_counter_gst) 
            {
                $get_order_id = $get_counter_gst[0]->prefix.$get_counter_gst[0]->counter.$get_counter_gst[0]->postfix;

                // for `gst` product    
                if ((count($get_gst_product)) > 0) 
                {
                    $gst_amount_total = 0;
                    foreach ($get_gst_product as $gst_product) {
                        $gst_amount_total += (($gst_product->rate) * ($gst_product->quantity));
                    }

                    $create_order_gst = OrderModel::create([
                        'user_id' => $userId,
                        'order_id' => $get_order_id,
                        'order_date' => Carbon::now(),
                        'amount' => $gst_amount_total,
                        'type' => 'gst',
                        'remarks' => $request->input('remarks'),
                        // ✅ add this line
                        'status' => ($userId == 489) ? 'completed' : 'pending',
                        // NEW fields for Quotation user
                        'name'       => $customerName,
                        'mobile'     => $customerMobile,
                    ]);

                    //order_table_id
                    foreach ($get_gst_product as $gst_product) {
                        // save every item in order_items with order_table_id
                        $create_order_items = OrderItemsModel::create([
                            'order_id' => $create_order_gst->id,
                            'product_code' => $gst_product->product_code,
                            'product_name' => $gst_product->product_name,
                            'rate' => $gst_product->rate,
                            'quantity' => $gst_product->quantity,
                            'total' => $gst_product->rate * $gst_product->quantity,
                            'size' => $gst_product->size,
                            'type' => $gst_product->type,
                            'remarks' => $gst_product->remarks,
                        ]);
                    }
                    
                }
            }

            // if ($create_order_basic != null) {
            //     $update_cart = CounterModel::where('name', 'order_basic')
            //     ->update([
            //         'counter' => (($get_counter_basic[0]->counter)+1),
            //     ]);
            // }
            if ($create_order_basic != null) {
                CounterModel::where('name', $counterNameBasic)->increment('counter');
            }
            

            // if($create_order_gst != null)
            // {
            //     $update_cart = CounterModel::where('name', 'order_gst')
            //                                 ->update([
            //                                 'counter' => (($get_counter_gst[0]->counter)+1),
            //     ]);
            // }
            if ($create_order_gst != null) {
                CounterModel::where('name', $counterNameGst)->increment('counter');
            }
            

            $data = [];

            // Check if data_basic exists and is not null, then add it to the array
            if (!empty($create_order_basic)) {
                $data[] = $create_order_basic;
            }

            // Check if data_gst exists and is not null, then add it to the array
            if (!empty($create_order_gst)) {
                $data[] = $create_order_gst;
            }

            // Remove items from the cart for the user
            $get_remove_items = CartModel::where('user_id', $userId)->delete();

            if ($create_order_basic !== null || $create_order_gst !== null) {
                $generate_order_invoice = new InvoiceController();

                // Iterate through the $data array and add the corresponding invoices
                foreach ($data as &$order) {
                    // Unset unwanted fields

                    // Check if the current order is of type 'basic' and has an id
                    if ($order->type === 'basic') {
                        // Generate invoice and append to the current order as 'pdf'
                        $order->pdf = $generate_order_invoice->generateorderInvoice($create_order_basic->id, [
                            'is_edited' => false
                        ]);
                        if (!$isQuotationUser) {
                            $order->packing_slip = $generate_order_invoice->generatePackingSlip($create_order_basic->id);
                        }
                    }
                    // Check if the current order is of type 'gst' and has an id
                    elseif ($order->type === 'gst') {
                        // Generate invoice and append to the current order as 'pdf'
                        $order->pdf = $generate_order_invoice->generateorderInvoice($create_order_gst->id, [
                            'is_edited' => false
                        ]);
                        if (!$isQuotationUser) {
                            $order->packing_slip = $generate_order_invoice->generatePackingSlip($create_order_gst->id);
                        }
                    }

                    unset($order->updated_at, $order->created_at, $order->id);

                }

                return response()->json([
                    'message' => 'Order created and Invoice generated successfully!',
                    'data' => $data
                ], 201);
            }


            else {
                return response()->json([
                    'message' => 'Sorry, failed to create order!',
                    'data' => 'Error!'
                ], 400);
            }  
        }  
    }

    public function orders_items(Request $request)
    {
        $request->validate([
            // 'orderID' => 'required',
            'order_id' => 'required',
            // 'item' => 'required',
            'product_code' => 'required',
            'product_name' => 'required',
            'rate' => 'required',
            // 'discount' => 'required',
            'quantity' => 'required',
            // 'line_total' => 'required',
            'total' => 'required',
        ]);

            $create_order_items = OrderItemsModel::create([
                // 'orderID' => $request->input('orderID'),
                'order_id' => $request->input('order_id'),
                // 'item' => $request->input('item'),
                'product_code' => $request->input('product_code'),
                'product_name' => $request->input('product_name'),
                'rate' => $request->input('rate'),
                // 'discount' => $request->input('discount'),
                'quantity' => $request->input('quantity'),
                // 'line_total' => $request->input('line_total'),
                'total' => $request->input('total'),
                'remarks' => $request->input('remarks'),
            ]);


        if (isset($create_order_items)) {
            return response()->json([
                'message' => 'Order Items created successfully!',
                'data' => $create_order_items
            ], 201);
        }

        else {
            return response()->json([
                'message' => 'Failed to create order items successfully!'
            ], 400);
        }    
    }
    
    public function cart(Request $request)
    {
        $get_user = Auth::User();

        if($get_user->role == 'admin')
        {
            $request->validate([
                'user_id' => 'required',
                'product_code' => 'required',
                'product_name' => 'required',
                'rate' => 'required',
                'quantity' => 'required',
                'type' => 'required',
            ]);
        }

        else
        {
            $request->merge(['user_id' => $get_user->id]);
        }
    
            $create_cart = CartModel::updateOrCreate(
				[
					'user_id' => $request->input('user_id'),
					'product_code' => $request->input('product_code'),
					'size' => $request->input('size'),
				], 
				[
					'product_name' => $request->input('product_name'),
					'rate' => $request->input('rate'),
					'quantity' => $request->input('quantity'),
					'amount' => ($request->input('rate')) * ($request->input('quantity')),
					'type' => $request->input('type'),
					'remarks' => $request->input('remarks'),
                    'size' => $request->input('size'),
				]
			);

            unset($create_cart['id'], $create_cart['created_at'], $create_cart['updated_at']);


        return isset($create_cart) && $create_cart !== null
        ? response()->json(['Cart created successfully!', 'data' => $create_cart], 201)
        : response()->json(['Failed to create cart successfully!'], 400);

    }

    public function counter(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'counter' => 'required',
        ]);

            $create_counter = CounterModel::create([
                'name' => $request->input('name'),
                'prefix' => $request->input('prefix'),
                'counter' => $request->input('counter'),
                'postfix' => $request->input('postfix'),
            ]);


        if (isset($create_counter)) {
            return response()->json([
                'message' => 'Counter record created successfully!',
                'data' => $create_counter
            ], 201);
        }

        else {
            return response()->json([
                'message' => 'Failed to create counter record successfully!',
                'data' => $create_counter
            ], 400);
        }    
    }

    public function make_invoice(Request $request)
    {
        // Decode the JSON string inside the 'data' key
        $json_data = json_decode($request->input('data'), true);

        if (is_array($json_data) && isset($json_data[0])) 
        {
            $data = $json_data[0]; // Access the first element of the decoded array

            // Create the invoice
            $create_invoice = InvoiceModel::create([
                'order_id' => $data['order_id'],
                'user_id' => $data['user_id'],
                'invoice_number' => $data['invoice_no'],
                'date' => $data['invoice_date'],
                'amount' => $data['amount'],
                'type' => $data['type'],
            ]);

            $invoice_items = $data['invoice_items']; // Get the invoice_items array

            // Initialize an array to store created invoice items
            $created_items = [];

            // Loop through the invoice items and insert each one
            foreach ($invoice_items as $item) {
                $created_item = InvoiceItemsModel::create([
                    'invoice_id' => $create_invoice->id,
                    'product_code' => $item['product_code'],
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'total' => $item['total'],
                    'type' => $item['type'],
                ]);

            //     // Add each created item to the array
            //     $created_items[] = $created_item->toArray(); // Convert object to array for manipulation
            // }

            // // Remove 'updated_at', 'created_at', and 'id' from each created item
            // foreach ($created_items as &$item) {
            //     unset($item['updated_at'], $item['created_at'], $item['id']);

            //     // Add the modified item to the array
            //     $created_items[] = $created_item_array;
            // }

            // // Prepare the data array
            // $get_data = [];

            // // Check if create_invoice exists and is not null, then add it to the array
            // if(!empty($create_invoice))
            // {
            //     $get_data[] = $create_invoice;
            // }

            // // Check if created_items exists and is not null, then add it to the array
            // if(!empty($created_items))
            // {
            //     $get_data[] = $created_items;

            //     unset($created_items->updated_at, $created_items->created_at, $created_items->id);
            // }

            // if ($create_invoice !== null || $created_items !== null) 
            // {

            //     $generate_invoice = new InvoiceController();
    
            //      // This will store the invoices generated for display or further processing
            //     $invoices = [];
    
            //     $invoices = $generate_invoice->generateInvoice($create_invoice->id);
    
            //     // Add invoices to the $data array under a specific key
            //     $get_data['invoices'] = $invoices;

            //     unset($create_invoice->updated_at, $create_invoice->created_at, $create_invoice->id);
            // }

            // Add each created item to the array
            $created_items[] = $created_item->toArray(); // Convert object to array for manipulation
        }

        // Generate the invoice PDF using the invoice ID
        $generate_invoice = new InvoiceController();
        $invoice_pdf = $generate_invoice->generateInvoice($create_invoice->id); // You need the invoice ID here

        // Prepare the data array
        $get_data = [];

        // Add the created invoice to $get_data, but keep the 'id' for generating the invoice PDF first
        if (!empty($create_invoice)) {
            $invoice_array = $create_invoice->toArray();
            unset($invoice_array['updated_at'], $invoice_array['created_at']); // Remove created_at and updated_at
            // Do not unset 'id' here yet because we need it for the invoice generation
            $get_data[] = $invoice_array;
        }

        // Add the created items to $get_data after unsetting unnecessary fields
        foreach ($created_items as &$item) {
            unset($item['updated_at'], $item['created_at'], $item['id']); // Remove fields from each item
        }

        if (!empty($created_items)) {
            $get_data[] = $created_items;
        }

            // Now, after the invoice PDF is generated, you can safely remove the invoice 'id'
            unset($get_data[0]['id']); // Remove 'id' from the invoice data in the final response

            // Add the invoice PDF link to the data
            $get_data['invoices'] = $invoice_pdf;


            // Return a detailed response with the created invoice and invoice items
            return response()->json([
                'message' => 'Invoice and items created successfully!',
                // 'invoice' => $create_invoice, // Return the created invoice
                // 'invoice_items' => $created_items // Return the created invoice items
                'data' => $get_data
            ], 200);
        } 
        else 
        {
            return response()->json(['message' => 'Invalid data format'], 400);
        }
    }

    public function uploadProductsImage(Request $request)
    {
        $request->validate([
            'product_code' => 'required|integer|exists:t_products,product_code',
            'product_image' => 'required|mimes:jpg'
        ]);

        $productCode = $request->input('product_code');
        $file = $request->file('product_image');

        // Rename file to product_code.jpg
        $filename = $productCode. '.jpg';

        // Define directories
        $productPath = public_path('storage/uploads/products');
        $productPdfPath = public_path('storage/uploads/products_pdf');

        // Create directories if they don't exist
        if(!file_exists($productPath))
        {
        mkdir($productPath, 0755, true);
        }

        // Create directories if they don't exist
        if(!file_exists($productPdfPath))
        {
        mkdir($productPdfPath, 0755, true);
        }

        // Save the file in both directories
        try{
            $file->move($productPath, $filename);
            copy($productPath . '/' . $filename, $productPdfPath . '/' .$filename);
        }

        catch(\Exception $e)
        {
            return response()->json(['error' => 'Failed to upload the file: ' . $e->getMessage()], 500);
        }

        $update_file_name = ProductModel::where('product_code', $request->input('product_code'))
                                        ->update([
                                            'product_image' => "/storage/uploads/products/{$filename}",
                                        ]);
        
        return ($update_file_name == 1)
        ? response()->json(['message' => 'New products file updated successfully!', 'data' => $update_file_name], 200)
        : response()->json(['message' => 'No changes detected.'], 304);
    }  

    // public function stock_cart_store(Request $request)
    // {
    //     $validated = $request->validate([
    //         'items' => 'required|array|min:1',
    //         'items.*.product_code' => 'required|string|exists:t_products,product_code',
    //         'items.*.product_name' => 'required|string|exists:t_products,product_name',
    //         'items.*.quantity' => 'required|integer|min:1',
    //         'items.*.godown_id' => 'required|integer|exists:t_godown,id',
    //         'items.*.type' => 'required|in:IN,OUT',
    //         'items.*.size' => 'nullable',
    //     ]);

    //     $createdItems = [];
    //     foreach ($validated['items'] as $item) {
    //         $createdItem = StockCartModel::create([
    //             'user_id' => Auth::id(),
    //             'product_code' => $item['product_code'],
    //             'product_name' => $item['product_name'],
    //             'quantity' => $item['quantity'],
    //             'godown_id' => $item['godown_id'],
    //             'type' => $item['type'],
    //             'size' => $item['size'] ?? null, // Optional size field
    //         ]);
    //         $createdItems[] = $createdItem->makeHidden(['id', 'created_at', 'updated_at']);
    //     }

    //     return !empty($createdItems)
    //         ? response()->json([
    //             'status' => true,
    //             'message' => 'Stock cart items created successfully.',
    //             'data' => $createdItems,
    //         ], 200)
    //         : response()->json([
    //             'status' => false,
    //             'message' => 'Failed to create stock cart items.',
    //         ], 200);
    // }

    public function stock_cart_store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_code' => 'required|string|exists:t_products,product_code',
            'items.*.product_name' => 'required|string|exists:t_products,product_name',
            'items.*.quantity'     => 'required|integer|min:1',
            'items.*.godown_id'    => 'required|integer|exists:t_godown,id',
            'items.*.type'         => 'required|in:IN,OUT',
            'items.*.size'         => 'nullable',
        ]);

        $userId = Auth::id();

        DB::beginTransaction();

        try {
            $createdItems = [];
            $updatedItems = [];
            $hadDuplicates = false;

            foreach ($validated['items'] as $item) {

                $where = [
                    'user_id'      => $userId,
                    'godown_id'    => $item['godown_id'],
                    'product_code' => $item['product_code'],
                    'type'         => $item['type'],
                    'size'         => $item['size'] ?? null,
                ];

                $existing = StockCartModel::where($where)->lockForUpdate()->first();

                if ($existing) {
                    $hadDuplicates = true;

                    // $existing->quantity = (int)$existing->quantity + (int)$item['quantity'];
                    // $existing->product_name = $item['product_name']; // optional sync
                    // $existing->save();

                    // $updatedItems[] = $existing->makeHidden(['id','created_at','updated_at']);
                    continue;
                }

                $created = StockCartModel::create([
                    'user_id'      => $userId,
                    'product_code' => $item['product_code'],
                    'product_name' => $item['product_name'],
                    'quantity'     => $item['quantity'],
                    'godown_id'    => $item['godown_id'],
                    'type'         => $item['type'],
                    'size'         => $item['size'] ?? null,
                ]);

                $createdItems[] = $created->makeHidden(['id','created_at','updated_at']);
            }

            DB::commit();

            // If duplicates found: quantities updated, but return error message as you wanted
            if ($hadDuplicates) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Product already in cart.',
                    'data'    => [
                        'created' => $createdItems,
                        'updated' => $updatedItems,
                    ],
                ], 200);
            }

            return response()->json([
                'status'  => true,
                'message' => 'Stock cart items created successfully.',
                'data'    => $createdItems,
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Stock cart create failed: '.$e->getMessage(), [
                'user_id' => $userId,
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Failed to create stock cart items.',
                'data'    => [],
            ], 200);
        }
    }

    public function createStockOrder(Request $request)
    {
        try {
            // Validate the request for remarks (optional)
            $validated = $request->validate([
                'remarks' => 'nullable|string|max:255',
            ]);

            // Fetch the current user's ID
            $userId = Auth::id();

            // Fetch the current date as the order date
            $orderDate = now()->toDateString();

            // Fetch cart items for the user
            $cartItems = StockCartModel::where('user_id', $userId)->get();

            if ($cartItems->isEmpty()) {
                return response()->json([
                    'message' => 'No items found in the stock cart for the user.',
                    'status' => 'false',
                ], 200);
            }

            // Determine the order type from the first cart item
            $orderType = $cartItems->first()->type;

            // Fetch counter details
            $counter = CounterModel::where('name', 'stock_order')->firstOrFail();

             // Generate the order_id
            $orderId = $counter->prefix . str_pad($counter->counter, 5, '0', STR_PAD_LEFT) . $counter->postfix;

            // Increment the counter
            $counter->increment('counter');

            // Create the stock order
            $stockOrder = StockOrdersModel::create([
                'order_id' => $orderId,
                'user_id' => $userId,
                'order_date' => $orderDate,
                'type' => $orderType,
                'pdf' => null, // Placeholder for PDF path (to be generated later)
                'remarks' => $validated['remarks'] ?? null,
            ]);

            // Create stock order items from the cart
            foreach ($cartItems as $item) {
                StockOrderItemsModel::create([
                    'stock_order_id' => $stockOrder->id,
                    'product_code' => $item->product_code,
                    'product_name' => $item->product_name,
                    'godown_id' => $item->godown_id,
                    'quantity' => $item->quantity,
                    'type' => $item->type,
                    'size' => $item->size,
                ]);
            }

            // Clear the stock cart after creating the order
            StockCartModel::where('user_id', $userId)->delete();

            $generate_stock_order_invoice = new InvoiceControllerZP();
            $stockOrder->pdf = $generate_stock_order_invoice->generatestockorderInvoice($stockOrder->id);

            return response()->json([
                'message' => 'Stock order created successfully.',
                'data' => [
                    'order_id' => $stockOrder->order_id,
                    'order_date' => $stockOrder->order_date,
                    'type' => $stockOrder->type,
                    'remarks' => $stockOrder->remarks,
                    'items' => $cartItems->map(function ($item) {
                        return $item->only(['product_code', 'product_name', 'godown_id', 'quantity', 'type', 'size']);
                    }),
                ],
                'status' => 'true',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while creating the stock order.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // special rate
    public function createSpecialRate(Request $request)
    {
        try {
            // Validate input
            $validated = $request->validate([
                'user_id'      => 'required|integer|exists:users,id',
                'product_code' => 'required|integer|exists:t_products,product_code',
                'rate'         => 'required|numeric|min:0',
            ]);

            // Create or update special rate (avoid duplicates)
            $specialRate = SpecialRateModel::create([
                    'user_id'      => $validated['user_id'],
                    'product_code' => $validated['product_code'],
                    'rate' => $validated['rate'],
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Special rate saved successfully.',
                'data'    => $specialRate
            ], 201);

        } catch (Exception $e) {
            // Log the error
            Log::error('SpecialRate Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while saving special rate.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    // job card
    public function createJobCard(Request $request)
    {
        $validated = $request->validate([
            'client_name'         => ['required', 'string', 'max:191'],
            'mobile'              => ['required', 'string', 'max:20'],
            'warranty'            => ['required', Rule::in(['in_warranty', 'outside_warranty'])],
            'serial_no'           => ['nullable', 'string', 'max:100'],
            'model_no'            => ['nullable', 'string', 'max:100'],
            'problem_description' => ['nullable', 'string'],
            'assigned_to'         => ['nullable', 'string', 'max:191'],
        ]);

        try {
            $jobCard = DB::transaction(function () use ($validated) {
                $name   = 'job_card';
                $padLen = 3;     // 001, 002, ...
                $prefix = 'ACE'; // default if counter prefix is null

                // Lock row to prevent duplicates in concurrent requests
                $counter = CounterModel::where('name', $name)->lockForUpdate()->first();

                // If not present, create starting at 1 (FIRST VALUE = 1)
                if (!$counter) {
                    $counter = CounterModel::create([
                        'name'    => $name,
                        'prefix'  => $prefix,
                        'counter' => 1,    // start at 1
                        'postfix' => '/25-26',
                    ]);
                }

                // Use the CURRENT value for this job, then increment
                $currentNumber = (int) $counter->counter;              // e.g., 1 for the first
                $serial        = str_pad((string)$currentNumber, $padLen, '0', STR_PAD_LEFT);
                $usePrefix     = $counter->prefix ?: $prefix;
                $usePostfix    = $counter->postfix ? ('-' . $counter->postfix) : '';
                $jobId         = $usePrefix . '-' . $serial . $usePostfix; // e.g., ACE-001

                // Create the job card
                $jobCard = JobCardModel::create([
                    'client_name'         => $validated['client_name'],
                    'job_id'              => $jobId,
                    'mobile'              => $validated['mobile'],
                    'warranty'            => $validated['warranty'],
                    'serial_no'           => $validated['serial_no']           ?? null,
                    'model_no'            => $validated['model_no']            ?? null,
                    'problem_description' => $validated['problem_description'] ?? null,
                    'assigned_to'         => $validated['assigned_to']         ?? null,
                ]);

                // Increment AFTER using it
                $counter->counter = $currentNumber + 1;
                $counter->save();

                return $jobCard;
            });

            return response()->json([
                'success' => true,
                'message' => 'Job card created successfully.',
                'data'    => $jobCard,
            ], 201);

        } catch (\Throwable $e) {
            \Log::error('JobCard create failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create job card.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}