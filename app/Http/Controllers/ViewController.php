<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;

use App\Models\ProductModel;

use App\Models\User;

use App\Models\OrderModel;

use App\Models\OrderItemsModel;

use App\Models\CartModel;

use App\Models\CounterModel;

use App\Models\CategoryModel;

use App\Models\SubCategoryModel;

use App\Models\StockCartModel;

use App\Models\StockOrdersModel;

use App\Models\StockOrderItemsModel;

use App\Models\GodownModel;

use App\Models\SpecialRateModel;

use App\Models\JobCardModel;

use Carbon\Carbon;

class ViewController extends Controller
{
    //

    // public function product()
    // {
    //     // $get_product_details = ProductModel::select('SKU','product_code','product_name','category','sub_category','product_image','basic','gst','mark_up')->get();
    //     $get_product_details = ProductModel::select('product_code','product_name','category','sub_category','product_image','basic','gst')->get();
        

    //     if (isset($get_product_details)) {
    //         return response()->json([
    //             'message' => 'Fetch data successfully!',
    //             'data' => $get_product_details
    //         ], 200);
    //     }

    //     else {
    //         return response()->json([
    //             'message' => 'Failed get data successfully!',
    //         ], 404);
    //     }    
    // }

    public function product()
    {
        // Fetch authenticated user or check if the user is a guest
        $user = Auth::user();

        // Get the product details
        $get_product_details = ProductModel::select(
            'product_code',
            'product_name',
            'category',
            'sub_category',
            'product_image',
            'basic',
            'guest_price',
            'gst'
        )->get();

        if ($get_product_details->isNotEmpty()) {
            // Modify the response for guest users
            $products = $get_product_details->map(function ($product) use ($user) {
                return [
                    'product_code' => $product->product_code,
                    'product_name' => $product->product_name,
                    'category' => $product->category,
                    'sub_category' => $product->sub_category,
                    'product_image' => $product->product_image,
                    // For guest users, set basic = guest_price and gst = 0
                    'basic' => $user && $user->role !== 'guest' ? $product->basic : $product->guest_price,
                    'gst' => $user && $user->role !== 'guest' ? $product->gst : 0,
                ];
            });

            return response()->json([
                'message' => 'Fetch data successfully!',
                'data' => $products,
            ], 200);
        }

        return response()->json([
            'message' => 'Failed to fetch data!',
        ], 404);
    }

    public function lng_product($lang = 'eng')
    {
        // $get_product_details = ProductModel::select('product_code','product_name', 'name_in_hindi','name_in_telugu','category','sub_category','product_image','basic','gst')
        //                                     ->whereIn('type', ['MACHINE', 'ACCESSORIES'])
        //                                     ->get();
        $get_product_details = ProductModel::select(
            'product_code',
            'product_name',
            'name_in_hindi',
            'name_in_telugu',
            'category',
            'sub_category',
            'product_image',
            'basic',
            'gst'
        )
        ->whereIn('type', ['MACHINE', 'ACCESSORIES'])
        ->orderByRaw("FIELD(type, 'MACHINE', 'ACCESSORIES')")
        ->orderBy('order_by')
        ->get();
                                        
        
        $processed_prd_rec = $get_product_details->map(function($prd_rec) use ($lang)
        {
            $product_name = $prd_rec->product_name;

            if($lang === 'hin' && !empty($prd_rec->name_in_hindi))
            {
                $product_name = $prd_rec->name_in_hindi;
            }

            elseif ($lang === 'tlg' && !empty($prd_rec->name_in_telugu)) {
                $product_name = $prd_rec->name_in_telugu;
            }

            return [
                // 'SKU' => $prd_rec->SKU,
                'product_code' => $prd_rec->product_code,
                'product_name' => $product_name,
                'category' => $prd_rec->category,
                'sub_category' => $prd_rec->sub_category,
                'product_image' => $prd_rec->product_image,
                'basic' => $prd_rec->basic,
                'gst' => $prd_rec->gst,
            ];
        });


        if (isset($get_product_details)) {
            return response()->json([
                'message' => 'Fetch data successfully!',
                'data' => $processed_prd_rec,
                'fetch_records' => count($processed_prd_rec)
            ], 200);
        }

        else {
            return response()->json([
                'message' => 'Failed get data successfully!',
            ], 404);
        }    
    }

    public function lng_product_public($lang = 'eng')
    {
        $get_product_details = ProductModel::select(
            'product_code',
            'product_name',
            'name_in_hindi',
            'name_in_telugu',
            'category',
            'sub_category',
            'product_image'
        )
        ->whereIn('type', ['MACHINE', 'ACCESSORIES'])
        ->orderByRaw("FIELD(type, 'MACHINE', 'ACCESSORIES')")
        ->orderBy('order_by')
        ->get();

        $processed_prd_rec = $get_product_details->map(function ($prd_rec) use ($lang) {
            $product_name = $prd_rec->product_name;

            if ($lang === 'hin' && !empty($prd_rec->name_in_hindi)) {
                $product_name = $prd_rec->name_in_hindi;
            } elseif ($lang === 'tlg' && !empty($prd_rec->name_in_telugu)) {
                $product_name = $prd_rec->name_in_telugu;
            }

            return [
                'product_code' => $prd_rec->product_code,
                'product_name' => $product_name,
                'category' => $prd_rec->category,
                'sub_category' => $prd_rec->sub_category,
                // Wrap image path with url() to get full URL
                'product_image' => $prd_rec->product_image ? url($prd_rec->product_image) : null,
            ];
        });

        if ($get_product_details->isNotEmpty()) {
            return response()->json([
                'message' => 'Fetch data successfully!',
                'data' => $processed_prd_rec,
                'fetch_records' => count($processed_prd_rec)
            ], 200);
        } else {
            return response()->json([
                'message' => 'Failed to get data successfully!',
            ], 404);
        }
    }

    public function get_product(Request $request)
    {
        // Retrieve offset and limit from the request with default values
        $offset = $request->input('offset', 0); // Default to 0 if not provided
        $limit = $request->input('limit', 10);  // Default to 10 if not provided
        $user_id = $request->input('user_id');  // Assuming the user ID is provided in the request

        // Ensure the offset and limit are integers and non-negative
        $offset = max(0, (int) $offset);
        $limit = max(1, (int) $limit);

        // Retrieve filter parameters if provided
        $search = $request->input('search', null);
        $category = $request->input('category', null);
        $subCategory = $request->input('sub_category', null);

        // Get the user type
		$user_type = User::select('type')->where('id', $user_id)->first();

		if ($user_type && $user_type->type == 'special') {
			// If user type is 'special', select special columns but alias them as 'basic' and 'gst'
			$query = ProductModel::select(
				'product_code', 
				'product_name', 
				'category', 
				'sub_category', 
				'product_image', 
				DB::raw('special_basic as basic'), 
				DB::raw('special_gst as gst'),
                'out_of_stock',
                'yet_to_launch'
			);
		}  else if ($user_type && $user_type->type == 'aakhambati') {
			// If user type is 'special', select special columns but alias them as 'basic' and 'gst'
			$query = ProductModel::select(
				'product_code', 
				'product_name', 
				'category', 
				'sub_category', 
				'product_image', 
				DB::raw('0 as basic'), 
				DB::raw('aakhambati_gst as gst'),
                'out_of_stock',
                'yet_to_launch'
			);
		} else if ($user_type && $user_type->type == 'outstation') {
            // If user type is 'special', select special columns but alias them as 'basic' and 'gst'
            $query = ProductModel::select(
				'product_code', 
				'product_name', 
				'category', 
				'sub_category', 
				'product_image', 
				DB::raw('outstation_basic as basic'), 
				DB::raw('outstation_gst as gst'),
                'out_of_stock',
                'yet_to_launch'
			);
        } else {
			// Default columns for non-special users
			$query = ProductModel::select(
				'product_code', 
				'product_name', 
				'category', 
				'sub_category', 
				'product_image', 
				'basic', 
				'gst'
			);
		}

        // Apply search filter if provided
        if ($search) {
            $query->where('product_name', 'like', "%{$search}%");
        }

        // Apply category filter if provided
        if ($category) {
            $query->where('category', $category);
        }

        // Apply sub-category filter if provided
        if ($subCategory) {
            $query->where('sub_category', $subCategory);
        }

        // Apply pagination
        $query->skip($offset)->take($limit);
        $get_products = $query->get();

        // Check if products are found
        if (isset($get_products) && !$get_products->isEmpty()) {

            // Loop through each product to check if it's in the cart
            foreach ($get_products as $product) {
                // Check if the product is in the user's cart
                $cart_item = CartModel::where('user_id', $user_id)
                    ->where('product_code', $product->product_code)
                    ->first();

                // If the product is in the cart, set cart details
                if ($cart_item) {
                    $product->in_cart = true;
                    $product->cart_quantity = $cart_item->quantity;
                    $product->cart_type = $cart_item->type;
                } else {
                    // If the product is not in the cart
                    $product->in_cart = false;
                    $product->cart_quantity = null;  // or 0, depending on your preference
                    $product->cart_type = null;
                }
            }

            return response()->json([
                'message' => 'Fetch data successfully!',
                'data' => $get_products
            ], 200);

        } else {
            return response()->json([
                'message' => 'Failed to fetch data!',
            ], 404);
        }
    }

    public function lng_get_product(Request $request, $lang = 'eng')
    {
        // Retrieve input parameters with defaults
        $offset = max(0, (int) $request->input('offset', 0));
        $limit = max(1, (int) $request->input('limit', 10));
        // $user_id = $request->input('user_id');
        $search = $request->input('search', null);
        $category = $request->input('category', null);
        $subCategory = $request->input('sub_category', null);

        $dropdown = $request->input('dropdown', false);

        $get_user = Auth::User();

        if ($get_user->role == 'user') {
            $user_id = $get_user->id;
            // Update the app_status column
            User::where('id', $user_id)->update([
                'app_status' => 1,
                'last_viewed' => now(), // Set the current timestamp
            ]);
            
        } else {
            $request->validate([
                'user_id' => 'required',
            ]);
            $user_id = $request->input('user_id');
        }

        // === Get user meta (type + new ss/mp flags) ===
        $userMeta = User::select('type', 'ss', 'mp')->where('id', $user_id)->first();
        // Get the user type
		$user_type = User::select('type')->where('id', $user_id)->first();

        // $admin_user_mobile = User::select('mobile')->where('id', $user_id)->first();
        $admin_user_mobile = User::where('id', $user_id)->value('mobile');

		if ($get_user->mobile == "+919951263652") {


            // If user type is 'special', select special columns but alias them as 'basic' and 'gst'
            $query = ProductModel::select(
                'product_code', 
                'product_name', 
                'category', 
                'sub_category', 
                'product_image', 
                'extra_images',
                'size',
                DB::raw('0 as basic'), 
                DB::raw('0 as gst'), 
                'out_of_stock',
                'yet_to_launch',
                'video_link'
            );

        } else if ($admin_user_mobile == "+919819084849") {

            // If user type is 'special', select special columns but alias them as 'basic' and 'gst'
            $query = ProductModel::select(
                'product_code', 
                'product_name', 
                'category', 
                'sub_category', 
                'product_image', 
                'extra_images',
                'size',
                DB::raw('0 as basic'), 
                DB::raw('mp_price as gst'), 
                'out_of_stock',
                'yet_to_launch',
                'video_link'
            );

        }  else if ($user_type && $user_type->type == 'outstation') {
                // If user type is 'special', select special columns but alias them as 'basic' and 'gst'
                $query = ProductModel::select(
                    'product_code', 
                    'product_name', 
                    'category', 
                    'sub_category', 
                    'product_image', 
                    'extra_images',
                    'size',
                    DB::raw('outstation_basic as basic'), 
                    DB::raw('outstation_gst as gst'),
                    'out_of_stock',
                    'yet_to_launch',
                    'video_link'
                );
        } else if ($user_type && $user_type->type == 'zeroprice') {


            // If user type is 'special', select special columns but alias them as 'basic' and 'gst'
            $query = ProductModel::select(
                'product_code', 
                'product_name', 
                'category', 
                'sub_category', 
                'product_image', 
                'extra_images',
                'size',
                DB::raw('0 as basic'), 
                DB::raw('0 as gst'), 
                'out_of_stock',
                'yet_to_launch',
                'video_link'
            );

        } 
        else if ($user_type && $user_type->type == 'special') {
			// If user type is 'special', select special columns but alias them as 'basic' and 'gst'
			$query = ProductModel::select(
				'product_code', 
				'product_name', 
				'category', 
				'sub_category', 
				'product_image', 
                'extra_images',
                'size',
				DB::raw('special_basic as basic'), 
				DB::raw('special_gst as gst'),
                'out_of_stock',
                'yet_to_launch',
                'video_link'
			);
		}
        else if ($user_type && $user_type->type == 'aakhambati') {
            $query = ProductModel::select(
				'product_code', 
				'product_name', 
				'category', 
				'sub_category', 
				'product_image', 
                'extra_images',
                'size',
				DB::raw('0 as basic'), 
				DB::raw('aakhambati_gst as gst'),
                'out_of_stock',
                'yet_to_launch',
                'video_link'
			);
		} 
        else if ($user_type && $user_type->type == 'guest') {


            // If user type is 'special', select special columns but alias them as 'basic' and 'gst'
            $query = ProductModel::select(
                'product_code', 
                'product_name', 
                'category', 
                'sub_category', 
                'product_image', 
                'extra_images',
                'size',
                DB::raw('guest_price as basic'), 
                DB::raw('0 as gst'), 
                'out_of_stock',
                'yet_to_launch',
                'video_link'
            );

        } else {
			// Default columns for non-special users
			$query = ProductModel::select(
				'product_code', 
				'product_name', 
				'category', 
				'sub_category', 
				'product_image', 
                'extra_images',
                'size',
				'basic', 
				'gst',
                'out_of_stock',
                'yet_to_launch',
                'video_link'
			);
		}

        // ====== APPLY SS / MP SERIES RULES HERE ======
        if ($userMeta) {
            $hasSs = (int) $userMeta->ss === 1;
            $hasMp = (int) $userMeta->mp === 1;

            // if both are 1 → show all products (no extra where)
            if ($hasSs && !$hasMp) {
                // ss = 1, mp = 0 → all products EXCEPT those starting with MP
                $query->where('product_code', 'not like', 'MP%');

                // note: S* products are already included because they are not MP*
            } elseif (!$hasSs && $hasMp) {
                // ss = 0, mp = 1 → only MP* + S*
                $query->where(function ($q) {
                    $q->where('product_code', 'like', 'MP%')
                    ->orWhere('product_code', 'like', 'S%');
                });
            }
            // else: ss=0, mp=0 or ss=1, mp=1 → no extra filter (show all)
        }
        // ====== END SS / MP SERIES RULES ======

        // Apply filters
        // if ($search) {
        //     $query->where(function ($q) use ($search) {
        //         $q->where('product_name', 'like', "%{$search}%")
        //           ->orWhere('product_code', 'like', "%{$search}%");
        //     });
        // }

        // Tokenized search logic
        if ($search) {
            $tokens = preg_split('/[\s\.\-\,]+/', mb_strtolower($search)); // Split search query into tokens
            $query->where(function ($q) use ($tokens) {
                foreach ($tokens as $token) {
                    $q->where(function ($subQ) use ($token) {
                        // Search for each token in both product_name and product_code
                        $subQ->orWhereRaw('LOWER(product_name) LIKE ?', ["%{$token}%"])
                            ->orWhereRaw('LOWER(product_code) LIKE ?', ["%{$token}%"]);
                    });
                }
            });
        }
        
        if ($category) {
            $query->where('category', $category);
        }
        if ($subCategory) {
            $query->where('sub_category', $subCategory);
        }

        // Apply pagination and get products
        $total_products_count = $query->count();
        $get_products = $query->orderByRaw("FIELD(type, 'MACHINE', 'SAFETY', 'ACCESSORIES', 'SPARE') ASC")
                                ->orderBy('order_by')
                                ->skip($offset)
                                ->take($limit)
                                ->get();

        // Collect product codes from the current page
        $productCodes = $get_products->pluck('product_code')->all();

        // Preload special rates for this user for these products (product_code => rate)
        $specialRates = SpecialRateModel::where('user_id', $user_id)
            ->whereIn('product_code', $productCodes)
            ->pluck('rate', 'product_code');


        // Process products for language and cart details
        $processed_prd_lang_rec = $get_products->map(function ($prd_rec) use ($lang, $user_id, $dropdown, $specialRates) {
            
            // Set product name based on the selected language
            $product_name = $prd_rec->product_name;
            if ($lang === 'hin' && !empty($prd_rec->name_in_hindi)) {
                $product_name = $prd_rec->name_in_hindi;
            } elseif ($lang === 'tlg' && !empty($prd_rec->name_in_telugu)) {
                $product_name = $prd_rec->name_in_telugu;
            }

            // Extract video ID from video_link
            if (!empty($prd_rec->video_link)) {
                // Parse the URL to get the query parameters
                parse_str(parse_url($prd_rec->video_link, PHP_URL_QUERY), $query_params);
                // Extract 'v' parameter (YouTube video ID) or other identifiers from shorts
                if (isset($query_params['v'])) {
                    $prd_rec->video_link = $query_params['v'];
                } else {
                    // For YouTube Shorts, get the last part of the URL
                    $prd_rec->video_link = basename(parse_url($prd_rec->video_link, PHP_URL_PATH));
                }
            }

            // If a special rate exists for this product for this client, override GST with that rate
            if (isset($specialRates[$prd_rec->product_code])) {
                $prd_rec->gst = (float) $specialRates[$prd_rec->product_code];
            }


            // Parse extra_images from the database column
            $prd_rec->extra_images = !empty($prd_rec->extra_images)
            ? explode(',', $prd_rec->extra_images)
            : [];

            // Check if the product is in the user's cart
            $cart_item = CartModel::where('user_id', $user_id)
                ->where('product_code', $prd_rec->product_code)
                ->first();

            // Check if the product code appears in other product's machine_part_no
            $has_spares = ProductModel::where('machine_part_no', 'like', "%{$prd_rec->product_code}%")
            ->where('product_code', '!=', $prd_rec->product_code) // Exclude the current product
            ->exists();

            // Return processed product data
            if($dropdown)
            {
                return [
                    // 'SKU' => $prd_rec->SKU,
                    'product_code' => $prd_rec->product_code,
                    'product_name' => $product_name,
                    'gst' => $prd_rec->gst,
                ];
            }else{
                return [
                    // 'SKU' => $prd_rec->SKU,
                    'product_code' => $prd_rec->product_code,
                    'product_name' => $product_name,
                    'category' => $prd_rec->category,
                    'sub_category' => $prd_rec->sub_category,
                    'product_image' => $prd_rec->product_image,
                    'extra_images' => $prd_rec->extra_images,
                    'size' => $prd_rec->size,
                    'basic' => $prd_rec->basic,
                    'gst' => $prd_rec->gst,
                    'out_of_stock' => $prd_rec->out_of_stock,
                    'yet_to_launch' => $prd_rec->yet_to_launch,
                    'video_link' => $prd_rec->video_link ?? null,
                    'in_cart' => $cart_item ? true : false,
                    'has_spares' => $has_spares,
                    'cart_quantity' => $cart_item->quantity ?? null,
                    'cart_type' => $cart_item->type ?? null,
                    'cart_remarks' => $cart_item->remarks ?? null,
                    
                ];
            }
        });

        $show_basic = false;
        if($user_id == 113 || $user_id == 98) {
            // If the user is admin or has a specific mobile number, show basic prices
            $show_basic = true;
        }

        // Return response based on the result
        return $processed_prd_lang_rec->isEmpty()
        ? response()->json(['Failed to fetch data!'], 404)
        : response()->json(['message' => 'Fetch data successfully!',
                'show_basic' => $show_basic,
                'data' => $processed_prd_lang_rec,
                'count' => $total_products_count], 200);
    }

    public function getLatestUpdate($platform)
    {
        // Hardcoded update data for Android and iOS
        if ($platform === 'android') {
            return response()->json([
                'platform' => 'android',
                'update_type' => "true", // Android update is forced
            ]);
        }

        if ($platform === 'ios') {
            return response()->json([
                'platform' => 'ios',
                'update_type' => "true", // iOS update is optional
            ]);
        }

        return response()->json(['message' => 'No updates found for this platform.'], 404);
    }

    public function get_spares(Request $request, $lang = 'eng', $code = null)
    {
        $get_user = Auth::User();

        if ($get_user->role == 'user') {
            $user_id = $get_user->id;

            // Fetch user type
            $user_type = User::select('type')->where('id', $user_id)->first();
        }
        else{
            // $request->validate([
            //     'user_id' => 'required',
            // ]);
            // $user_id = $request->input('user_id');
            // $user_type = User::select('type')->where('id', $user_id)->first();
            $user_type = (object) ['type' => 'normal'];
        }

        // Base query for products
        $productQuery = ProductModel::select('product_code', 'product_name', 'name_in_hindi', 'name_in_telugu', 'category', 'sub_category', 'product_image', 'out_of_stock', 'yet_to_launch');

        // Add pricing columns dynamically based on user type
        if ($get_user->mobile == "+919951263652") {
            $productQuery->addSelect(
                DB::raw('0 as basic'), 
                DB::raw('0 as gst')
            );
        }elseif ($user_type && $user_type->type == 'outstation') {
            $productQuery->addSelect(
                DB::raw('outstation_basic as basic'), 
                DB::raw('outstation_gst as gst')
            );
        } elseif ($user_type && $user_type->type == 'zeroprice') {
            $productQuery->addSelect(
                DB::raw('0 as basic'), 
                DB::raw('0 as gst')
            );
        } elseif ($user_type && $user_type->type == 'special') {
            $productQuery->addSelect(
                DB::raw('special_basic as basic'), 
                DB::raw('special_gst as gst')
            );
        } elseif ($user_type && $user_type->type == 'aakhambati') {
            $productQuery->addSelect(
                DB::raw('0 as basic'), 
                DB::raw('aakhambati_gst as gst')
            );
		} elseif ($user_type && $user_type->type == 'guest') {
            $productQuery->addSelect(
                DB::raw('guest_price as basic'), 
                DB::raw('0 as gst')
            );
        } else {
            $productQuery->addSelect('basic', 'gst');
        }

        // Filter products by type and optionally machine part number
        $productQuery->where('type', 'SPARE');

        if ($code !== null) {
            $productQuery->where('machine_part_no', 'like', "%{$code}%");
        }

        // Execute query
        $get_spare_product = $productQuery->get();

        // Map the results for the response
        $spare_prd_rec = $get_spare_product->map(function ($spare_prd_rec) use ($lang) {
            $product_name = $spare_prd_rec->product_name;

            // Language-specific product names
            if ($lang === 'hin' && !empty($spare_prd_rec->name_in_hindi)) {
                $product_name = $spare_prd_rec->name_in_hindi;
            } elseif ($lang === 'tlg' && !empty($spare_prd_rec->name_in_telugu)) {
                $product_name = $spare_prd_rec->name_in_telugu;
            }

            return [
                'product_code' => $spare_prd_rec->product_code,
                'product_name' => $product_name,
                'category' => $spare_prd_rec->category,
                'sub_category' => $spare_prd_rec->sub_category,
                'product_image' => $spare_prd_rec->product_image,
                'basic' => $spare_prd_rec->basic,
                'gst' => $spare_prd_rec->gst,
                'out_of_stock' => $spare_prd_rec->out_of_stock,
                'yet_to_launch' => $spare_prd_rec->yet_to_launch,
            ];
        });

        // Return response
        return isset($spare_prd_rec) && $spare_prd_rec->isNotEmpty()
            ? response()->json(['message' => 'Fetch data successfully!', 'data' => $spare_prd_rec, 'fetch_records' => count($spare_prd_rec)], 200)
            : response()->json(['message' => 'Failed to get data'], 404);
    }

    public function get_spares_new(Request $request, $lang = 'eng', $code = null)
    {
        $get_user = Auth::User();

        if ($get_user->role == 'user') {
            $user_id = $get_user->id;

            // Fetch user type
            $user_type = User::select('type')->where('id', $user_id)->first();
        }
        else{
            $request->validate([
                'user_id' => 'required',
            ]);
            $user_id = $request->input('user_id');
            $user_type = User::select('type')->where('id', $user_id)->first();
            // $user_type = (object) ['type' => 'normal'];
        }

        // Base query for products
        $productQuery = ProductModel::select('product_code', 'product_name', 'name_in_hindi', 'name_in_telugu', 'category', 'sub_category', 'product_image', 'out_of_stock', 'yet_to_launch');

        // Add pricing columns dynamically based on user type
        if ($user_type && $user_type->type == 'special') {
            $productQuery->addSelect(
                DB::raw('special_basic as basic'), 
                DB::raw('special_gst as gst')
            );
        } elseif ($user_type && $user_type->type == 'aakhambati') {
            $productQuery->addSelect(
                DB::raw('0 as basic'), 
                DB::raw('aakhambati_gst as gst')
            );
		} elseif ($user_type && $user_type->type == 'outstation') {
            $productQuery->addSelect(
                DB::raw('outstation_basic as basic'), 
                DB::raw('outstation_gst as gst')
            );
        } elseif ($user_type && $user_type->type == 'zeroprice') {
            $productQuery->addSelect(
                DB::raw('0 as basic'), 
                DB::raw('0 as gst')
            );
        } else if ($get_user->mobile == "+919951263652") {
            $productQuery->addSelect(
                DB::raw('0 as basic'), 
                DB::raw('0 as gst')
            );
        }elseif ($user_type && $user_type->type == 'guest') {
            $productQuery->addSelect(
                DB::raw('guest_price as basic'), 
                DB::raw('0 as gst')
            );
        } else {
            $productQuery->addSelect('basic', 'gst');
        }

        // Filter products by type and optionally machine part number
        $productQuery->where('type', 'SPARE');

        if ($code !== null) {
            $productQuery->where('machine_part_no', 'like', "%{$code}%");
        }

        // Execute query
        $get_spare_product = $productQuery->get();

        // Map the results for the response
        $spare_prd_rec = $get_spare_product->map(function ($spare_prd_rec) use ($lang) {
            $product_name = $spare_prd_rec->product_name;

            // Language-specific product names
            if ($lang === 'hin' && !empty($spare_prd_rec->name_in_hindi)) {
                $product_name = $spare_prd_rec->name_in_hindi;
            } elseif ($lang === 'tlg' && !empty($spare_prd_rec->name_in_telugu)) {
                $product_name = $spare_prd_rec->name_in_telugu;
            }

            return [
                'product_code' => $spare_prd_rec->product_code,
                'product_name' => $product_name,
                'category' => $spare_prd_rec->category,
                'sub_category' => $spare_prd_rec->sub_category,
                'product_image' => $spare_prd_rec->product_image,
                'basic' => $spare_prd_rec->basic,
                'gst' => $spare_prd_rec->gst,
                'out_of_stock' => $spare_prd_rec->out_of_stock,
                'yet_to_launch' => $spare_prd_rec->yet_to_launch,
            ];
        });

        // Return response
        return isset($spare_prd_rec) && $spare_prd_rec->isNotEmpty()
            ? response()->json(['message' => 'Fetch data successfully!', 'data' => $spare_prd_rec, 'fetch_records' => count($spare_prd_rec)], 200)
            : response()->json(['message' => 'Failed to get data'], 404);
    }

    public function categories()
    {
        // Fetch all categories with their product count
        $categories = CategoryModel::withCount('get_products')->get();

        // Filter and format the categories data for a JSON response
		$formattedCategories = $categories->map(function ($category) {
			// Only include categories with products_count > 0
			if ($category->get_products_count > 0) {
				return [
					'category_id' => $category->id,
					'category_name' => $category->name,
					'category_image' => $category->image,
					'products_count' => $category->get_products_count,
				];
			}
			return null; // Return null for categories with 0 products
		})->filter(); // Remove null values

        if (isset($formattedCategories)) {
            return response()->json([
                'message' => 'Fetch data successfully!',
                'data' => $formattedCategories,
                'count' => count($formattedCategories),
            ], 200);
        }

        else {
            return response()->json([
                'message' => 'Failed get data successfully!',
            ], 404);
        }    
    }

    public function hash_key()
    {
        $plain = 'FqbrARWCmCvH4o3CFZXb4jJcBer4GOScYi1NPPKN815bef91';
        $hash = hash('sha256', $plain);

        return response()->json([
            'hash_key' => $hash,
        ], 200);
    }
    
    public function sub_categories($category = null)
    {
        // Convert the string of category IDs to an array, e.g., '1,2' -> [1, 2]
        $categoryIds = $category ? explode(',', $category) : [];

        // // Fetch subcategories filtered by category_id if provided
        // $sub_categories = SubCategoryModel::withCount('products')
        // ->when($category, function ($query, $category) {
        //     // Filter subcategories by the category_id if a category is provided
        //     return $query->where('category_id', $category);
        // })->get();

        // Fetch subcategories filtered by multiple category_ids if provided
        $sub_categories = SubCategoryModel::withCount('products')
        ->when(!empty($categoryIds), function ($query) use ($categoryIds) {
            // Filter subcategories by multiple category_ids using whereIn
            return $query->whereIn('category_id', $categoryIds);
        })->get();

        // Format the categories data for a JSON response
        $formattedSubCategories = $sub_categories->map(function ($sub_category) {
            return [
                'sub_category_name' => $sub_category->name,
                'sub_category_image' => $sub_category->image,
                'sub_products_count' => $sub_category->products_count,
            ];
        });
        
        if (isset($formattedSubCategories)) {
            return response()->json([
                'message' => 'Fetch data successfully!',
                'data' => $formattedSubCategories,
                'count' => count($formattedSubCategories),
            ], 200);
        }

        else {
            return response()->json([
                'message' => 'Failed get data successfully!',
            ], 400);
        }    
    }

    public function lng_sub_categories($category = null, $lang = 'eng')
    {
        $categoryIds = $category ? explode(',', $category) : [];

        // Fetch subcategories filtered by multiple category_ids if provided
        $sub_categories = SubCategoryModel::withCount('products')
        ->when(!empty($categoryIds), function ($query) use ($categoryIds) 
        {
            // Filter subcategories by multiple category_ids using whereIn
            return $query->whereIn('category_id', $categoryIds);
        })
        ->get();

        // Format the subcategories data for a JSON response
        $formattedSubCategories = $sub_categories->map(function ($sub_category) use ($lang) 
        {
            // Set the sub-category name based on the selected language
            $sub_category_name = $sub_category->name; // Default to English

            if ($lang === 'hin' && !empty($sub_category->name_in_hindi)) {
                $sub_category_name = $sub_category->name_in_hindi;
            } elseif ($lang === 'tlg' && !empty($sub_category->name_in_telugu)) {
                $sub_category_name = $sub_category->name_in_telugu;
            }

            return [
                'sub_category_name' => $sub_category_name,
                'sub_category_image' => $sub_category->image,
                'sub_products_count' => $sub_category->products_count,
            ];
        });
        
        return $formattedSubCategories->isEmpty()
        ? response()->json(['Failed get data successfully!'], 404)
        : response()->json(['message' => 'Fetch data successfully!',
                'data' => $formattedSubCategories,
                'count' => count($formattedSubCategories)], 200);
    }

    public function lng_categories($lang = 'eng')
	{
		// Fetch all categories with their product count
		$categories = CategoryModel::withCount('get_products')->orderBy('order_by')->get();

		// Format and filter the categories data for a JSON response
		$formattedCategories = $categories->map(function ($category) use ($lang) {
			$category_name = $category->name;

			// Set category name based on language
			if ($lang === 'hin' && !empty($category->name_in_hindi)) {
				$category_name = $category->name_in_hindi;
			} elseif ($lang === 'tlg' && !empty($category->name_in_telugu)) {
				$category_name = $category->name_in_telugu;
			}

			// Return category details if products count > 0, otherwise return null
			return $category->get_products_count > 0 ? [
				'category_id' => $category->id,
				'category_name' => $category_name,
				'category_image' => $category->image,
				'products_count' => $category->get_products_count,
                'order_by'       => $category->order_by,
			] : null;
		})->filter(); // Filter out null values

		// Check if the filtered categories are empty and return response
		return $formattedCategories->isEmpty()
			? response()->json(['message' => 'No categories with products found!'], 404)
			: response()->json([
				'message' => 'Fetch data successfully!',
				'data' => $formattedCategories->values(), // Re-index filtered array
				'count' => $formattedCategories->count(),
			], 200);
	}

    public function lng_categories_public($lang = 'eng')
    {
        // Fetch all categories with their product count
        $categories = CategoryModel::withCount('get_products')->get();

        // Format and filter the categories data for a JSON response
        $formattedCategories = $categories->map(function ($category) use ($lang) {
            $category_name = $category->name;

            // Set category name based on language
            if ($lang === 'hin' && !empty($category->name_in_hindi)) {
                $category_name = $category->name_in_hindi;
            } elseif ($lang === 'tlg' && !empty($category->name_in_telugu)) {
                $category_name = $category->name_in_telugu;
            }

            // Return category details if products count > 0, otherwise return null
            return $category->get_products_count > 0 ? [
                'category_id' => $category->id,
                'category_name' => $category_name,
                // Wrap image path with url() helper
                'category_image' => $category->image ? url($category->image) : null,
                'products_count' => $category->get_products_count,
            ] : null;
        })->filter(); // Filter out null values

        // Check if the filtered categories are empty and return response
        return $formattedCategories->isEmpty()
            ? response()->json(['message' => 'No categories with products found!'], 404)
            : response()->json([
                'message' => 'Fetch data successfully!',
                'data' => $formattedCategories->values(), // Re-index filtered array
                'count' => $formattedCategories->count(),
            ], 200);
    }

    public function user($lang = 'eng')
    {
        $get_user_details = User::select('id', 'name', 'name_in_hindi', 'name_in_telugu', 'email', 'mobile', 'role', 'address_line_1', 'address_line_2', 'city', 'pincode', 'gstin', 'state', 'country', 'is_verified', 'type', 'mp_type', 'app_status', 'purchase_lock', 'ss', 'mp', 'purchase_limit')
                                ->where('role', 'user')->orderBy('name', 'asc')
                                ->get();
    
        $processed_rec_user = $get_user_details->map(function ($record) use ($lang) {
            $name = $record->name;
    
            if ($lang == 'hin' && !empty($record->name_in_hindi)) {
                $name = $record->name_in_hindi;
            } elseif ($lang == 'tlg' && !empty($record->name_in_telugu)) {
                $name = $record->name_in_telugu;
            }

            $currentTimestamp = now();
            $lastViewedTimestamp = \Carbon\Carbon::parse($record->last_viewed);

            $differenceInSeconds = $currentTimestamp->diffInSeconds($lastViewedTimestamp);

            $last_viewed = '';

            if ($differenceInSeconds < 60) {
                $last_viewed = (int) $differenceInSeconds . ' seconds ago';
            } elseif ($differenceInSeconds < 3600) {
                $minutes = (int) floor($differenceInSeconds / 60);
                $last_viewed = $minutes . ' minutes ago';
            } elseif ($differenceInSeconds < 86400) {
                $hours = (int) floor($differenceInSeconds / 3600);
                $last_viewed = $hours . ' hours ago';
            } else {
                $days = (int) floor($differenceInSeconds / 86400);
                $last_viewed = $days . ' days ago';
            }
    
            return [
                'id' => $record->id,
                'name' => $name,
                'email' => $record->email,
                'mobile' => $record->mobile,
                'city' => $record->city,
                'role' => ucfirst($record->role),
                'address' => implode(', ', array_filter([$record->address_line_1, $record->address_line_2, $record->city, $record->state, $record->pincode, $record->country])),
                'gstin' => $record->gstin,
                'type' => $record->type,
                'mp_type' => $record->mp_type,
                'ss' => $record->ss,
                'mp' => $record->mp,
                'app_status' => $record->app_status,
                'verified' => $record->is_verified,
                'last_viewed' => $record->app_status == 1 ? $last_viewed : '',
                'purchase_lock' => $record->purchase_lock,
                'purchase_limit' => $record->purchase_limit,
                
            ];
        });
    
        $types = [
            ['value' => 'normal', 'name' => 'Normal'],
            ['value' => 'special', 'name' => 'Special'],
            ['value' => 'outstation', 'name' => 'Outstation'],
            ['value' => 'zeroprice', 'name' => 'Zero Price'],
            ['value' => 'aakhambati', 'name' => 'AA Khambati Price'],
        ];
    
        return $processed_rec_user->isEmpty()
            ? response()->json(['Failed get data successfully!'], 404)
            : response()->json(['Fetch data successfully!', 'data' => $processed_rec_user, 'types' => $types, 'admin_type' => 'owner'], 200);
    }

    public function find_user($search = null)
    {   
        if ($search == null) {
            $get_user_details = User::select('id','name','email','mobile','role','address_line_1','address_line_2','city','pincode','gstin','state','country')
                                ->get();     
        }
        else {
            $get_user_details = User::select('id','name','email','mobile','role','address_line_1','address_line_2','city','pincode','gstin','state','country')
                                ->where('name', $search)
                                ->orWhere('mobile', $search)
                                ->get();     
        }

        if (isset($get_user_details) && (!$get_user_details->isEmpty())) {
            return response()->json([
                'message' => 'Fetch record successfully!',
                'data' => $get_user_details
            ], 200);
        }

        else {
            return response()->json([
                'message' => 'Failed get data successfully!',
            ], 404);
        }    
    }

    public function user_details()
    {
        $get_user_id = Auth::id();
        
        $get_user_details = User::select('id','name','email','mobile','address_line_1','address_line_2','city','pincode','gstin','state','country')->where('id', $get_user_id)->get();
        

        if (isset($get_user_details)) {
            return response()->json([
                'message' => 'Fetch data successfully!',
                'data' => $get_user_details
            ], 200);
        }

        else {
            return response()->json([
                'message' => 'Failed get data successfully!',
            ], 404);
        }    
    }

    public function orders()
    {
        $get_all_orders = OrderModel::with('user')->orderBy('created_at', 'desc')->get();

        if (isset($get_all_orders)) {
            return response()->json([
                'message' => 'Fetch data successfully!',
                'data' => $get_all_orders
            ], 200);
        }

        else {
            return response()->json([
                'message' => 'Fetch data successfully!',
                'data' => []
            ], 200);
        }    
    }

    public function orders_user_id(Request $request, $id = null)
    {
        $get_user = Auth::User();

        if ($get_user->role == 'user') {
            $id = $get_user->id;
        } else {
            $request->validate([
                'user_id' => 'required',
            ]);
            $id = $request->input('user_id');
        }

        $user_id = $id;

        // Fetch all orders and their associated order items with product image
        $get_user_orders = OrderModel::where('user_id', '!=', 489)
            ->when($id, function ($query, $id) {
            return $query->where('user_id', $id);
        })
        ->with(['order_items' => function($query) {
            // Eager load product relationship and append the product_image field
            $query->with('product:id,product_code,product_image');
        }])
        ->orderBy('created_at', 'desc')
        ->get();

        // Modify the order items to append the product image directly
        $get_user_orders->each(function($order) {
            $get_user = Auth::User();
            if ($get_user->mobile == "+919951263652") {
                $order->amount = 0;
                $order->order_invoice = $order->packing_slip;
            }
            $order->order_items->each(function($orderItem) {
                $orderItem->product_image = $orderItem->product->product_image ?? null;
                // $orderItem->product_image = $orderItem->product->product_image ? url($orderItem->product->product_image) : null;
                unset($orderItem->product); // Remove the product object after extracting the image
            });
        });

        $show_basic = false;
        if($user_id == 113 || $user_id == 98) {
            // If the user is admin or has a specific mobile number, show basic prices
            $show_basic = true;
        }

        if ($get_user_orders->isEmpty()) {
            return response()->json([
                'message' => 'Fetch data successfully!',
                'show_basic' => $show_basic,
                'data' => []
            ], 200);
        } else {
            return response()->json([
                'message' => 'Fetched data successfully!',
                'show_basic' => $show_basic,
                'data' => $get_user_orders
            ], 200);
        }
    }

    public function order_items()
    {
        $get_all_order_items = OrderItemsModel::with('get_orders')->get();
        

        if (isset($get_all_order_items)) {
            return response()->json([
                'message' => 'Fetch data successfully!',
                'data' => $get_all_order_items
            ], 200);
        }

        else {
            return response()->json([
                'message' => 'Failed get data successfully!',
            ], 404);
        }    
    }

    public function orders_items_order_id($id)
    {
        $get_items_for_orders = OrderItemsModel::where('orderID', $id)->get();
        // $get_items_for_orders = OrderItemsModel::where('order_id', $id)
        // ->join()
        // ->get();
        

        if (isset($get_items_for_orders)) {
            return response()->json([
                'message' => 'Fetch data successfully!',
                'data' => $get_items_for_orders
            ], 200);
        }

        else {
            return response()->json([
                'message' => 'Failed get data successfully!',
            ], 404);
        }    
    }

    public function getPendingOrders()
    {
        try {
            // Attempt to fetch orders where the status is 'pending' and load the related user information
            $pendingOrders = OrderModel::where('status', 'pending')
                ->with('user:id,name', 'order_items.product:id,product_code,product_image',) // Eager load the 'user' and 'order_items' relationships
                ->orderByDesc('id')
                ->get();

            // add product_image_url and hide the 'product' relation on each item
            $pendingOrders->transform(function($order) {
                $order->order_items->transform(function($item) {
                    $item->product_image = $item->product
                        ? ($item->product->product_image)
                        : null;

                    // hide the raw product relation
                    $item->makeHidden('product');

                    return $item;
                });
                return $order;
            });

            // Return the response with the orders and their respective user names
            return response()->json([
                'success' => true,
                'data' => $pendingOrders->makeHidden(['product'])
            ]);
        } catch (Exception $e) {
            // If an error occurs, catch the exception and return an error response
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching pending orders.',
                'error' => $e->getMessage()
            ], 500); // HTTP 500 for internal server error
        }
    }

    public function cart()
    {
        // Retrieve all records with their associated user and product data
        $get_all_cart_records = CartModel::with(['get_users', 'get_products'])->get();
        

        // Transform the data if needed
        $formattedData = $get_all_cart_records->map(function ($item) {
			
            return [
                'id' => $item->id, // Adjust as necessary
                'user' => $item->get_users ? [
                    'id' => $item->get_users->id,
                    'name' => $item->get_users->name, // Adjust fields as necessary
                ] : null,
                'product' => $item->get_products ? [
                    'product_code' => $item->get_products->product_code,
                    'name' => $item->get_products->product_name, // Adjust fields as necessary
                ] : null,
            ];
        });
        if (isset($formattedData)) {
            return response()->json([
                'message' => 'Fetch all recods successfully!',
                'data' => $formattedData
            ], 200);
        }

        else {
            return response()->json([
                'message' => 'Failed fetch records successfully!',
            ], 400);
        }    
    }

    public function cart_user($id = null)
    {
        $get_user = Auth::User();

        if ($get_user->role == 'admin') {

            $user_type = User::select('type')->where('id', $id)->first();
            $user_id = $id;
            $basic_column = 'basic';
            $gst_column = 'gst';

            if ($user_type && $user_type->type == 'special') {
                $basic_column = DB::raw('special_basic as basic');
                $gst_column = DB::raw('special_gst as gst');
            } elseif ($user_type && $user_type->type == 'aakhambati') {
                $basic_column = DB::raw('0 as basic');
                $gst_column = DB::raw('aakhambati_gst as gst');
            } elseif ($user_type && $user_type->type == 'outstation') {
                $basic_column = DB::raw('outstation_basic as basic');
                $gst_column = DB::raw('outstation_gst as gst');
            } elseif ($user_type && $user_type->type == 'zeroprice') {
                $basic_column = DB::raw('0 as basic');
                $gst_column = DB::raw('0 as gst');
            } elseif ($user_type && $user_type->type == 'guest') {
                $basic_column = DB::raw('guest_price as basic');
                $gst_column = DB::raw('0 as gst');
            }
            
            $get_items_for_user = CartModel::where('t_cart.user_id', $id)
                ->join('t_products', 't_cart.product_code', '=', 't_products.product_code')
                ->select(
                    't_cart.id',
                    't_cart.user_id',
                    't_cart.product_code',
                    't_cart.product_name',
                    't_cart.rate',
                    't_cart.quantity',
                    't_cart.amount',
                    't_cart.type',
                    't_cart.remarks',
                    't_cart.size',
                    't_products.product_image',
                    $basic_column,
                    $gst_column
                )
                ->get();

            $cart_data_count = count($get_items_for_user);
        } else {

            $user_type = User::select('type')->where('id', $get_user->id)->first();
            $user_id = $get_user->id;
            $basic_column = 'basic';
            $gst_column = 'gst';

            if ($user_type && $user_type->type == 'special') {
                $basic_column = DB::raw('special_basic as basic');
                $gst_column = DB::raw('special_gst as gst');
            } elseif ($user_type && $user_type->type == 'aakhambati') {
                $basic_column = DB::raw('0 as basic');
                $gst_column = DB::raw('aakhambati_gst as gst');
            }elseif ($user_type && $user_type->type == 'outstation') {
                $basic_column = DB::raw('outstation_basic as basic');
                $gst_column = DB::raw('outstation_gst as gst');
            } elseif ($user_type && $user_type->type == 'zeroprice') {
                $basic_column = DB::raw('0 as basic');
                $gst_column = DB::raw('0 as gst');
            }elseif ($user_type && $user_type->type == 'guest') {
                $basic_column = DB::raw('guest_price as basic');
                $gst_column = DB::raw('0 as gst');
            }
            
            $get_items_for_user = CartModel::where('t_cart.user_id', $get_user->id)
                ->join('t_products', 't_cart.product_code', '=', 't_products.product_code')
                ->select(
                    't_cart.id',
                    't_cart.user_id',
                    't_cart.product_code',
                    't_cart.product_name',
                    't_cart.rate',
                    't_cart.quantity',
                    't_cart.amount',
                    't_cart.type',
                    't_cart.remarks',
                    't_cart.size',
                    't_products.product_image',
                    $basic_column,
                    $gst_column
                )
                ->get();
        }

        $show_basic = false;
        if($user_id == 113 || $user_id == 98) {
            // If the user is admin or has a specific mobile number, show basic prices
            $show_basic = true;
        }
    
        if (isset($get_items_for_user)) {
            return response()->json([
                'message' => 'Fetch data successfully!',
                'show_basic' => $show_basic,
                'data' => $get_items_for_user,
                'record count' => count($get_items_for_user)
            ], 200);
        }

        else {
            return response()->json([
                'message' => 'Failed get data successfully!',
            ], 404);
        }    
    }

    public function counter()
    {
        $get_counter_records = CounterModel::all();
        
        if (isset($get_counter_records)) {
            return response()->json([
                'message' => 'Fetch data successfully!',
                'data' => $get_counter_records
            ], 200);
        }

        else {
            return response()->json([
                'message' => 'Failed get data successfully!',
            ], 404);
        }    
    }

    public function dashboard_details()
    {
        $get_product_numbers = ProductModel::count();
        $get_user_numbers = User::count();
        $get_order_numbers = OrderModel::count();

        $get_dashboard_records = array([
            'total_users' => $get_user_numbers,
            'total_products' => $get_product_numbers,
            'total_orders' => $get_order_numbers,
        ]);
        
        if (isset($get_dashboard_records)) {
            return response()->json([
                'message' => 'Fetch records successfully!',
                'data' => $get_dashboard_records
            ], 200);
        }

        else {
            return response()->json([
                'message' => 'Sorry, failed get records',
            ], 404);
        }    
    }

    public function return_order($orderId)
    {
        // \DB::enableQueryLog();
        $get_order_details = OrderModel::with('order_items')
                                        ->where('id', $orderId)
                                        ->get();
                                        // dd(\DB::getQueryLog());


        if ($get_order_details) 
        {
            if ($get_order_details[0]->type == 'Basic') {
                $get_invoice_id = CounterModel::where('name', 'invoice_basic')
                                                ->get();

                $return_invoice_id = $get_invoice_id[0]->prefix.$get_invoice_id[0]->counter.$get_invoice_id[0]->postfix;
            }
            else {
                $get_invoice_id = CounterModel::where('name', 'invoice_basic')
                ->get();

                $return_invoice_id = $get_invoice_id[0]->prefix.$get_invoice_id[0]->counter.$get_invoice_id[0]->postfix;
            }

            $formatted_order_record = 
            [
                'id' => $get_order_details[0]->id,
                'order_id' => $get_order_details[0]->order_id,
                'user_id' => $get_order_details[0]->user_id,
                'order_date' => $get_order_details[0]->order_date ? $get_order_details[0]->order_date : null,
                'amount' => $get_order_details[0]->amount,
                'status' => $get_order_details[0]->status,
                'type' => ucfirst($get_order_details[0]->type),
                'order_invoice' => $get_order_details[0]->order_invoice,
                'order_invoice_id' => $return_invoice_id,
                'order_items' => $get_order_details[0]->order_items->map(function ($item) {
                    return 
                    [
                        'product_code' => $item->product_code,
                        'product_name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'rate' => $item->rate,
                        'type' => ucfirst($item->type ?? '')  
                    ];
                })->toArray()
            ];
        }                                                                    
        

        if (empty($formatted_order_record)) 
        {
            return response()->json(['message' => 'Failed to get order records!'], 400);
        } 
        else 
        {
            return response()->json([
                'message' => 'Fetch records successfully!',
                'data' => $formatted_order_record
            ], 200);
        }
    }

    public function stock_cart_index($id = null)
    {
        // Fetch stock cart items for the authenticated user
        $stockCartItems = StockCartModel::with(['godown:id,name'])
            ->where('user_id', Auth::id());

        // Check if a specific item is requested
        if ($id) {
            $item = $stockCartItems->find($id);

            // Use a ternary operator to validate and return the response
            return $item
                ? response()->json([
                    'message' => 'Stock cart item fetched successfully.',
                    'data' => [
                        'id' => $item->id,
                        'product_code' => $item->product_code,
                        'product_name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'type' => $item->type,
                        'size' => $item->size,
                        'godown_id' => $item->godown_id,
                        'godown_name' => $item->godown->name ?? null,
                        'product_image' => ProductModel::where('product_code', $item->product_code)->value('product_image'),
                    ],
                    'count' => 1,
                ], 200)
                : response()->json([
                    'message' => 'Stock cart item not found.',
                    'count' => 0,
                ], 404);
        }

        // Fetch all items for the user
        $items = $stockCartItems->get();

        // Process items to include additional details
        $processedItems = $items->map(function ($item) {
            return [
                'id' => $item->id,
                'product_code' => $item->product_code,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'type' => $item->type,
                'size' => $item->size,
                'godown_id' => $item->godown_id,
                'godown_name' => $item->godown->name ?? null,
                'product_image' => ProductModel::where('product_code', $item->product_code)->value('product_image'),
            ];
        });

        // Use a ternary operator to validate and return the response
        return $processedItems->isNotEmpty()
            ? response()->json([
                'message' => 'Stock cart items fetched successfully.',
                'data' => $processedItems,
                'count' => $processedItems->count(),
            ], 200)
            : response()->json([
                'message' => 'No stock cart items found for this user.',
                'data' => [],
                'count' => 0,
            ], 200);
    }

    public function fetchStockOrder($orderId = null)
    {
        try {
            if ($orderId) {
                // Fetch specific stock order with its items
                $stockOrder = StockOrdersModel::with(['items', 'user'])
                    ->where('id', $orderId)
                    ->first();

                if (!$stockOrder) {
                    return response()->json([
                        'message' => 'Stock order not found.',
                        'status' => 'false',
                    ], 200);
                }

                return response()->json([
                    'message' => 'Stock order fetched successfully.',
                    'data' => [
                        'id' => $stockOrder->id,
                        'order_id' => $stockOrder->order_id,
                        'order_date' => $stockOrder->order_date,
                        'type' => $stockOrder->type,
                        'remarks' => $stockOrder->remarks,
                        'attachment' => $stockOrder->pdf,
                        'user' => $stockOrder->user ? $stockOrder->user->name : '-',
                        'items' => $stockOrder->items->map(function ($item) {
                            return array_merge($item->only(['product_code', 'product_name','godown_id', 'quantity', 'type', 'size']), [
                                'product_image' => ProductModel::where('product_code', $item->product_code)->value('product_image'),
                            ]);
                        }),
                    ],
                    'status' => 'true',
                ], 200);
            } else {
                // Fetch all stock orders with pagination
                $stockOrders = StockOrdersModel::with(['items', 'user'])
                    ->orderBy('order_date', 'desc')
                    ->paginate(10);

                return response()->json([
                    'message' => 'Stock orders fetched successfully.',
                    'data' => $stockOrders->map(function ($order) {
                        return [
                            'id' => $order->id,
                            'order_id' => $order->order_id,
                            'order_date' => $order->order_date,
                            'type' => $order->type,
                            'remarks' => $order->remarks,
                            'attachment' => $order->pdf,
                            'user' => $order->user ? $order->user->name : '-',
                            'items' => $order->items->map(function ($item) {
                                return array_merge($item->only(['product_code', 'product_name', 'godown_id', 'quantity', 'type', 'size']), [
                                    'product_image' => ProductModel::where('product_code', $item->product_code)->value('product_image'),
                                ]);
                            }),
                        ];
                    }),
                    'status' => 'true',
                    'count' => $stockOrders->total(),
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching stock orders.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function get_godown($productCode = null)
    {
        if ($productCode == null) {
            $get_godown_record = GodownModel::select('id', 'name', 'description')
                                    ->where('name', '!=', 'DIRECT DISPATCH')
                                    ->get();

                                
            return response()->json([
                'message' => 'Godown records fetched successfully!',
                'data' => $get_godown_record,
                'status' => 'true',
            ], 200);
        }else{
            try {        
                 // Fetch all godowns
                $godowns = GodownModel::select('id', 'name', 'description')->where('name', '!=', 'DIRECT DISPATCH')->get()->keyBy('id');

                // Fetch stock order items for the product code
                $stockOrderItems = StockOrderItemsModel::with('godown:name,id')
                    ->select('godown_id', 'type', 'quantity')
                    ->where('product_code', $productCode)
                    ->get();

                // Fetch stock cart items for the product code
                $stockCartItems = StockCartModel::with('godown:name,id')
                    ->select('godown_id', 'type', 'quantity')
                    ->where('product_code', $productCode)
                    ->get();

                // Prepare the final output
                $result = $godowns->map(function ($godown) use ($stockOrderItems, $stockCartItems) {
                    $stockItems = $stockOrderItems->where('godown_id', $godown->id);
                    $cartItems = $stockCartItems->where('godown_id', $godown->id);

                    $totalInStock = $stockItems->where('type', 'IN')->sum('quantity');
                    $totalOutStock = $stockItems->where('type', 'OUT')->sum('quantity');
                    $totalInCart = $cartItems->where('type', 'IN')->sum('quantity');
                    $totalOutCart = $cartItems->where('type', 'OUT')->sum('quantity');

                return [
                    'id' => $godown->id,
                    'name' => $godown->name,
                    'description' => $godown->description,
                    'current_stock' => $totalInStock - $totalOutStock,
                    'hold_in' => $totalInCart,
                    'hold_out' => $totalOutCart,
                ];
            });

                return response()->json([
                    'message' => 'Stock details fetched successfully!',
                    'data' => $result->values(),
                    'status' => 'true',
                ], 200);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'An error occurred while fetching stock details.',
                    'error' => $e->getMessage(),
                ], 500);
            }
        
        }
    }

    // public function product_stock_details(Request $request)
    // {
    //     $productCodes = $request->input('product_code') ? explode(',', $request->input('product_code')) : null;
    //     $godownId = $request->input('godown_id');
    //     $userId = $request->input('user_id');
    //     $startDate = $request->input('start_date');
    //     $endDate = $request->input('end_date');

    //     // Default date range: last 3 months
    //     $startDate = $startDate ?? now()->subMonths(3)->startOfDay();
    //     $endDate = $endDate ?? now()->endOfDay();

    //     try {
    //         // Fetch stock orders with optional filters
    //         $stockOrders = StockOrdersModel::with(['user', 'items.godown', 'items.stock_product'])
    //             ->when($userId, function ($query, $userId) {
    //                 $query->where('user_id', $userId);
    //             })
    //             ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
    //                 $query->whereBetween('order_date', [$startDate, $endDate]);
    //             })
    //             ->when(!$startDate && !$endDate, function ($query) {
    //                 $query->whereBetween('order_date', [now()->subMonths(3)->startOfDay(), now()->endOfDay()]);
    //             })
    //             ->get();

    //         // Check if any stock orders exist
    //         if ($stockOrders->isEmpty()) {
    //             return response()->json([
    //                 'message' => 'No stock orders found for the given criteria.',
    //                 'data' => [],
    //                 'status' => 'false',
    //             ], 404);
    //         }

    //         // Map results to the desired format and flatten them
    //         $result = $stockOrders->flatMap(function ($order) use ($productCodes, $godownId) {
    //             return $order->items->filter(function ($item) use ($productCodes, $godownId) {
    //                 return (!$productCodes || in_array($item->product_code, $productCodes)) &&
    //                     (!$godownId || $item->godown_id == $godownId);
    //             })->map(function ($item) use ($order) {
    //                 return [
    //                     'date' => $order->created_at->format('Y-m-d'),
    //                     'product_code' => $item->product_code,
    //                     'product_name' => $item->product_name ?? 'Unknown',
    //                     'godown_name' => $item->godown->name ?? 'Unknown',
    //                     'quantity' => $item->quantity,
    //                     'type' => $item->type,
    //                     'user' => $order->user->name ?? 'Unknown',
    //                 ];
    //             });
    //         });

    //         // Sort by date in descending order and convert to array
    //         $sortedResult = $result->sortByDesc('date')->values();

    //         return response()->json([
    //             'message' => 'Stock orders fetched successfully!',
    //             'data' => $sortedResult,
    //             'status' => 'true',
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => 'An error occurred while fetching stock records.',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    public function product_stock_details(Request $request)
    {
        $productCodes = $request->input('product_code') ? explode(',', $request->input('product_code')) : null;
        $godownId = $request->input('godown_id');
        $userId = $request->input('user_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Default date range: last 3 months
        $startDate = $startDate ?? now()->subMonths(3)->startOfDay();
        $endDate = $endDate ?? now()->endOfDay();

        try {
            // Fetch stock orders with related models
            $stockOrders = StockOrdersModel::with([
                'user',
                'items.godown',
                'items.stock_product',
            ])
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->when($startDate && $endDate, fn($q) => $q->whereBetween('order_date', [$startDate, $endDate]))
            ->get();

            if ($stockOrders->isEmpty()) {
                return response()->json([
                    'message' => 'No stock orders found for the given criteria.',
                    'data' => [],
                    'status' => 'false',
                ], 404);
            }

            $result = $stockOrders->flatMap(function ($order) use ($productCodes, $godownId) {
                return $order->items->filter(function ($item) use ($productCodes, $godownId) {
                    return (!$productCodes || in_array($item->product_code, $productCodes)) &&
                        (!$godownId || $item->godown_id == $godownId);
                })->map(function ($item) use ($order) {

                    // Default godown name
                    $godownName = $item->godown->name ?? 'Unknown';

                    // Fetch linked order details (if exists)
                    $linkedOrder = null;
                    $linkedOrderId = null;
                    $linkedOrderUser = null;
                    $clientName = null;

                    // If linked order exists, fetch its details
                    if (!empty($order->t_order_id)) {
                        $linkedOrder = \App\Models\OrderModel::with('user')
                            ->find($order->t_order_id);

                        if ($linkedOrder) {
                            $linkedOrderId = $linkedOrder->order_id ?? 'N/A';
                            $linkedOrderUser = $linkedOrder->user->name ?? 'Unknown';
                            $clientName = $linkedOrder->user->name ?? 'Unknown'; // Assuming client name is in the 'client' relation
                            $godownName .= "\n({$linkedOrderUser})";
                        }
                    }

                    return [
                        'date' => $order->created_at->format('Y-m-d'),
                        'product_code' => $item->product_code,
                        'product_name' => $item->product_name ?? 'Unknown',
                        'godown_name' => $godownName,
                        'quantity' => $item->quantity,
                        'type' => $item->type,
                        'user' => $order->user->name ?? 'Unknown',
                        'order_no' => $linkedOrderId, // Add order number
                        'client_name' => $clientName, // Add client name
                    ];
                });
            });

            $sortedResult = $result->sortByDesc('date')->values();

            return response()->json([
                'message' => 'Stock orders fetched successfully!',
                'data' => $sortedResult,
                'status' => 'true',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching stock records.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // public function product_stock_details(Request $request)
    // {
    //     $productCodes = $request->input('product_code') ? explode(',', $request->input('product_code')) : null;
    //     $godownId    = $request->input('godown_id');
    //     $userId      = $request->input('user_id');
    //     $startDate   = $request->input('start_date');
    //     $endDate     = $request->input('end_date');

    //     // Default date range: last 3 months
    //     $startDate = $startDate ?? now()->subMonths(3)->startOfDay();
    //     $endDate   = $endDate ?? now()->endOfDay();

    //     try {
    //         $stockOrders = StockOrdersModel::with([
    //                 'user',
    //                 'items.godown',
    //                 'items.stock_product',
    //             ])
    //             ->when($userId, fn ($q) => $q->where('user_id', $userId))
    //             ->when($startDate && $endDate, fn ($q) => $q->whereBetween('order_date', [$startDate, $endDate]))
    //             ->get();

    //         if ($stockOrders->isEmpty()) {
    //             return response()->json([
    //                 'message' => 'No stock orders found for the given criteria.',
    //                 'data'    => [],
    //                 'status'  => 'false',
    //             ], 404);
    //         }

    //         $result = $stockOrders->flatMap(function ($order) use ($productCodes, $godownId) {
    //             return $order->items
    //                 ->filter(function ($item) use ($productCodes, $godownId) {
    //                     return (!$productCodes || in_array($item->product_code, $productCodes))
    //                         && (!$godownId || $item->godown_id == $godownId);
    //                 })
    //                 ->map(function ($item) use ($order) {
    //                     // Default godown name
    //                     $godownName = $item->godown->name ?? 'Unknown';

    //                     // Linked order details (if any)
    //                     $linkedOrder      = null;
    //                     $linkedOrderId    = null;
    //                     $linkedOrderUser  = null;
    //                     $clientName       = null;

    //                     if (!empty($order->t_order_id)) {
    //                         $linkedOrder = \App\Models\OrderModel::with('user')->find($order->t_order_id);

    //                         if ($linkedOrder) {
    //                             $linkedOrderId   = $linkedOrder->order_id ?? 'N/A';
    //                             $linkedOrderUser = $linkedOrder->user->name ?? 'Unknown';
    //                             $clientName      = $linkedOrder->user->name ?? null; // may be null/empty
    //                             $godownName     .= "\n({$linkedOrderUser})";
    //                         }
    //                     }

    //                     // 👉 If client_name is empty, fall back to stock order remarks
    //                     if (is_null($clientName) || trim($clientName) === '') {
    //                         $clientName = $order->remarks ?? 'Unknown';
    //                     }

    //                     return [
    //                         'date'         => $order->created_at?->format('Y-m-d') ?? ($order->order_date ? \Carbon\Carbon::parse($order->order_date)->format('Y-m-d') : null),
    //                         'product_code' => $item->product_code,
    //                         'product_name' => $item->product_name ?? 'Unknown',
    //                         'godown_name'  => $godownName,
    //                         'quantity'     => $item->quantity,
    //                         'type'         => $item->type,
    //                         'user'         => $order->user->name ?? 'Unknown',
    //                         'order_no'     => $linkedOrderId,
    //                         'client_name'  => $clientName,
    //                     ];
    //                 });
    //         });

    //         $sortedResult = $result->sortByDesc('date')->values();

    //         return response()->json([
    //             'message' => 'Stock orders fetched successfully!',
    //             'data'    => $sortedResult,
    //             'status'  => 'true',
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => 'An error occurred while fetching stock records.',
    //             'error'   => $e->getMessage(),
    //         ], 500);
    //     }
    // }


    /**
     * GET /api/admin/products/pending/{product_code}
     * Response:
     * {
     *   current_stock: int,
     *   pending_orders: [{client_name, order_no, order_date, qty}],
     *   balance: int
     * }
     */
    public function productPendingSummary(string $product_code)
    {
        // 1) CURRENT STOCK = total IN - total OUT
        $inQty = DB::table('t_stock_order_items')
            ->where('product_code', $product_code)
            ->where('type', 'IN')
            ->sum('quantity');

        $outQty = DB::table('t_stock_order_items')
            ->where('product_code', $product_code)
            ->where('type', 'OUT')
            ->sum('quantity');

        $currentStock = (int) $inQty - (int) $outQty;

        // 2) PENDING ORDERS
        $pending = OrderItemsModel::query()
            ->from('t_order_items as oi')
            ->selectRaw('
                o.order_id   as order_no,
                o.order_date as order_date,
                u.name       as client_name,
                SUM(oi.quantity) as qty
            ')
            ->join('t_orders as o', 'o.id', '=', 'oi.order_id')
            ->join('users as u', 'u.id', '=', 'o.user_id')
            ->where('oi.product_code', $product_code)
            ->where('o.status', 'pending')
            ->groupBy('o.id', 'o.order_id', 'o.order_date', 'u.name')
            ->orderBy('o.order_date', 'asc')
            ->get()
            ->map(function ($row) {
                return [
                    'client_name' => $row->client_name,
                    'order_no'    => $row->order_no,
                    'order_date'  => Carbon::parse($row->order_date)->format('j/n/Y'),
                    'qty'         => (int) $row->qty,
                ];
            });

        // 3) BALANCE
        $totalPendingQty = (int) $pending->sum('qty');
        $balance = $currentStock - $totalPendingQty;

        return response()->json([
            'current_stock'  => $currentStock,
            'pending_orders' => $pending->values(),
            'balance'        => $balance,
        ]);
    }

    public function orderGodownStock($orderId)
    {
        try {
            // 1) Load order + items
            $order = OrderModel::with(['order_items', 'user'])->find($orderId);
            if (!$order) {
                return response()->json([
                    'status'  => false,
                    'message' => "Order not found for id: {$orderId}",
                    'data'    => []
                ], 404);
            }

            if ($order->order_items->isEmpty()) {
                return response()->json([
                    'status'  => true,
                    'message' => 'Order has no items.',
                    'data'    => []
                ], 200);
            }

            // 2) Identify DIRECT DISPATCH godown (by name, case-insensitive)
            $directDispatchGodown = DB::table('t_godown')
                ->select('id', 'name')
                ->whereRaw('LOWER(name) = ?', ['direct dispatch'])
                ->first();

            // Fallback: allow env/config override if you keep an ID in config/inventory.php
            if (!$directDispatchGodown && config('inventory.direct_dispatch_godown_id')) {
                $ddId = (int) config('inventory.direct_dispatch_godown_id');
                $directDispatchGodown = DB::table('t_godown')->select('id','name')->where('id', $ddId)->first();
            }

            if (!$directDispatchGodown) {
                return response()->json([
                    'status'  => false,
                    'message' => 'DIRECT DISPATCH godown not found. Create a godown named "DIRECT DISPATCH" or set inventory.direct_dispatch_godown_id.',
                    'data'    => []
                ], 422);
            }

            // 3) Collect product codes from the order
            $productCodes = $order->order_items->pluck('product_code')->filter()->unique()->values()->all();
            if (empty($productCodes)) {
                return response()->json([
                    'status'  => true,
                    'message' => 'No product codes found in order items.',
                    'data'    => []
                ], 200);
            }

            // 🔹 NEW: Get product images mapped by product_code
            $productImagesByCode = DB::table('t_products')
            ->select('product_code', 'product_image')
            ->whereIn('product_code', $productCodes)
            ->pluck('product_image', 'product_code'); // [code => url]

            // 4) Build current available stock per product per godown
            //    available = SUM(IN) - SUM(OUT) ; negative becomes 0
            //    Table assumed: t_stock_order_items (columns: product_code, godown_id, quantity, type IN/OUT)
            $rawStocks = DB::table('t_stock_order_items as soi')
                ->select([
                    'soi.product_code',
                    'soi.godown_id',
                    DB::raw("GREATEST(SUM(CASE WHEN soi.type = 'IN'  THEN soi.quantity ELSE 0 END) 
                                    - SUM(CASE WHEN soi.type = 'OUT' THEN soi.quantity ELSE 0 END), 0) as available")
                ])
                ->whereIn('soi.product_code', $productCodes)
                ->groupBy('soi.product_code', 'soi.godown_id')
                ->havingRaw('available > 0')
                ->get();

            // Get godown names for display
            $godownIds = $rawStocks->pluck('godown_id')->unique()->values()->all();
            if (!in_array($directDispatchGodown->id, $godownIds, true)) {
                $godownIds[] = $directDispatchGodown->id;
            }
            $godownMap = DB::table('t_godown')
                ->whereIn('id', $godownIds)
                ->pluck('name', 'id'); // [id => name]

            // Reindex stocks by product_code => [ [godown_id, available], ... ]
            $stocksByProduct = [];
            foreach ($rawStocks as $row) {
                $stocksByProduct[$row->product_code][] = [
                    'godown_id'   => (int) $row->godown_id,
                    'godown_name' => (string) ($godownMap[$row->godown_id] ?? 'Unknown'),
                    'available'   => (float) $row->available,
                ];
            }
            // Sort each product’s godown list by highest available first
            foreach ($stocksByProduct as &$list) {
                usort($list, function ($a, $b) {
                    if ($a['available'] === $b['available']) return 0;
                    return ($a['available'] < $b['available']) ? 1 : -1;
                });
            }
            unset($list);

            // 5) Allocate per item
            $itemsOutput = [];
            foreach ($order->order_items as $item) {
                $requestedQty = (float) $item->quantity;
                $remaining    = $requestedQty;
                $allocations  = [];

                // pull stock list for this product_code
                $stockList = $stocksByProduct[$item->product_code] ?? [];

                foreach ($stockList as $stockRow) {
                    if ($remaining <= 0) break;
                    if ($stockRow['available'] <= 0) continue;

                    $take = min($remaining, $stockRow['available']);
                    if ($take > 0) {
                        $allocations[] = [
                            'godown_id'   => $stockRow['godown_id'],
                            'godown_name' => $stockRow['godown_name'],
                            'qty'         => $take,
                            'source'      => 'stock',
                        ];
                        $remaining -= $take;
                    }
                }

                // If short, assign the rest to DIRECT DISPATCH
                if ($remaining > 0) {
                    $allocations[] = [
                        'godown_id'   => (int) $directDispatchGodown->id,
                        'godown_name' => (string) $directDispatchGodown->name,
                        'qty'         => $remaining,
                        'source'      => 'direct_dispatch',
                    ];
                }

                // 🔹 Assign product image from lookup map
                $productImage = $productImagesByCode[$item->product_code] ?? null;

                $itemsOutput[] = [
                    'order_item_id' => $item->id ?? null,
                    'product_code'  => $item->product_code,
                    'product_name'  => $item->product_name ?? null,
                    'product_image' => $productImage,   // 👈 added
                    'size'          => $item->size ?? null,
                    'qty'           => $requestedQty,
                    'rate'          => (float) $item->rate,   // 👈 include item price
                    'total'         => (float) $item->total,  // optional: line total
                    'allocations'   => $allocations,
                ];
            }

            // 6) Response
            return response()->json([
                'status'  => true,
                'message' => 'Godown allocations generated successfully.',
                'order'   => [
                    'id'         => $order->id,
                    'order_id'   => $order->order_id,
                    'order_date' => $order->order_date,
                    'user_id'    => $order->user_id,
                    'client_name'=> $order->user->name ?? 'N/A',  // 👈 add client name
                    'status'     => $order->status,
                    'type'       => $order->type,
                ],
                'data'    => $itemsOutput,
            ], 200);

        } catch (\Throwable $e) {
            // Log error for debugging
            \Log::error('orderGodownStock error', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'An error occurred while generating godown allocations.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function fetchSpecialRate($id = null)
    {
        try {
            // If no user_id provided, fetch all users' special rates
            if ($id === null) {
                $rates = SpecialRateModel::with(['user:id,name,mobile,city,type'])
                    ->select('id', 'user_id', 'product_code', 'rate')
                    ->orderBy('id', 'desc')
                    ->get();
            } else {
                // Fetch special rates for the given user_id
                $rates = SpecialRateModel::with(['user:id,name,mobile,city,type'])
                    ->select('id', 'user_id', 'product_code', 'rate')
                    ->where('user_id', $id)
                    ->orderBy('id', 'desc')
                    ->get();
            }

            // If no rates are found, return an empty array
            if ($rates->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data'    => [],
                ], 200);
            }

            // Get user type for calculating the original rate
            $user = $rates->first()->user; // Assuming user info is already loaded
            $user_type = $user->type;

            // Build the user block
            $payload = $rates->groupBy('user_id')->map(function ($userRates) use ($user_type) {
                $user = $userRates->first()->user; // eager load the user details

                return [
                    'user_id' => (string)($user->id ?? ''),
                    'name'    => (string)($user->name ?? ''),
                    'mobile'  => (string)($user->mobile ?? ''),
                    'city'    => (string)($user->city ?? ''),
                    'type'    => (string)($user->type ?? ''),
                    'special_rate' => $userRates->map(function ($r) use ($user_type) {
                        // Fetch product details by product_code
                        $product = ProductModel::where('product_code', $r->product_code)->first();

                        // Set the original rate based on user type
                        $original_rate = 0;

                        if ($user_type == 'special') {
                            $original_rate = $product->special_gst ?? 0;
                        } elseif ($user_type == 'outstation') {
                            $original_rate = $product->outstation_gst ?? 0;
                        } elseif ($user_type == 'zeroprice') {
                            $original_rate = 0;
                        } elseif ($user_type == 'guest') {
                            $original_rate = $product->guest_price ?? 0;
                        } elseif ($user_type == 'aakhambati') {
                            $original_rate = $product->aakhambati_gst ?? 0;
                        } else {
                            // Default for other users (if needed)
                            $original_rate = $product->gst ?? 0;
                        }

                        return [
                            'id'            => (string)$r->id,
                            'product_code'  => (string)$r->product_code,
                            'product_name'  => (string)($product->product_name ?? 'Unknown'), // Fetch product name
                            'rate'          => (string)$r->rate,
                            'original_rate' => (string)$original_rate,
                        ];
                    })->values(),
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data'    => $payload,
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('Fetch Special Rates Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching special rates.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }



    /**
     * FETCH: all or by id (route param).
     * GET /job-cards           -> all
     * GET /job-cards/{id}      -> single
     */
    public function fetchJobCard($id = null)
    {
        try {
            if ($id) {
                $job = JobCardModel::select(
                    'id',
                    'client_name',
                    'job_id',
                    'mobile',
                    'warranty',
                    'serial_no',
                    'model_no',
                    'problem_description',
                    'assigned_to',
                )->find($id);

                if (!$job) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Job card not found.',
                    ], 404);
                }

                return response()->json([
                    'success' => true,
                    'data'    => $job,
                ], 200);
            }

            // All
            $jobs = JobCardModel::select(
                'id',
                'client_name',
                'job_id',
                'mobile',
                'warranty',
                'serial_no',
                'model_no',
                'problem_description',
                'assigned_to',
            )
            ->orderBy('id', 'desc')
            ->get();

            return response()->json([
                'success' => true,
                'data'    => $jobs,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('JobCard fetch error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching job cards.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // return blade file
    
    public function login_view()
    {
        return view('login');
    }

    public function user_view()
    {
        return view('view_user');
    }
}