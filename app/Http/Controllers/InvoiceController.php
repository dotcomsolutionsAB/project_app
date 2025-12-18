<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;        
use App\Models\OrderModel;    
use App\Models\OrderItemsModel;
use App\Models\InvoiceModel;
use App\Models\InvoiceItemsModel;
use App\Models\CategoryModel;
use App\Models\ProductModel;
use Mpdf\Mpdf;
// use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use App\Utils\sendWhatsAppUtility;
use App\Models\StockOrderItemsModel;
use DB;
use Carbon\Carbon;

ini_set('memory_limit', '512M'); // Adjust as needed
set_time_limit(300); // Increase timeout to 5 minutes or as needed


class InvoiceController extends Controller
{
    //
    public function generateorderInvoice_old($orderId, $is_edited = false, $is_merged = false, $merged_orders = '', $is_split = false, $old_order_id = '')
    {
        // $get_user = Auth::id();
        
        $order = OrderModel::select('user_id','order_id', 'amount', 'order_date','type', 'remarks')
                            ->where('id', $orderId)
                            ->first();

        $get_user = $order->user_id;
        
        $user = User::select('name', 'mobile', 'email', 'address_line_1', 'address_line_2', 'gstin')
                    ->where('id', $get_user)
                    ->first();
		

        $order_items = OrderItemsModel::with('product:product_code')
                                    ->select('product_code', 'product_name', 'rate', 'quantity', 'total', 'remarks', 'size')
                                    ->where('order_id', $orderId)
                                    ->get();
        $mobileNumbers = User::where('role', 'admin')->pluck('mobile')->toArray();
        

        if (!$user || !$order || $order_items->isEmpty()) {
            return response()->json(['error' => 'Sorry, required data are not available!'], 500);
        }

        $sanitizedOrderId = preg_replace('/[^A-Za-z0-9]+/', '-', trim($order->order_id));
        $sanitizedOrderId = trim($sanitizedOrderId, '-');

		//die($sanitizedOrderId);
		
        $data = [
            'user' => $user,
            'order' => $order,
            'order_items' => $order_items,
        ];
		$mpdf = new Mpdf();

        /*$html = view('order_invoice_template', $data)->render();

        
        $mpdf->WriteHTML($html);

        $publicPath = 'uploads/orders/';
        $fileName = 'invoice_' . $sanitizedOrderId . '.pdf';
        $filePath = storage_path('app/public/' . $publicPath . $fileName);

        if (!File::isDirectory($storage_path = storage_path('app/public/' . $publicPath))) {
            File::makeDirectory($storage_path, 0755, true);
        }

        $mpdf->Output($filePath, 'F');*/
		
		// Load initial HTML for header and customer information.
		// Render the header
		$headerHtml = view('order_invoice_template_header', ['user' => $user, 'order' => $order])->render();
		$mpdf->WriteHTML($headerHtml);

		$chunkSize = 10;
		$orderItems = collect($order_items)->chunk($chunkSize);

		foreach ($orderItems as $chunk) {
			foreach ($chunk as $index => $item) {
				// Render each item row individually
				$htmlChunk = view('order_invoice_template_items', compact('item', 'index'))->render();
				$mpdf->WriteHTML($htmlChunk);
			}
			//ob_flush();
			flush();
		}


		// Render the footer
		$footerHtml = view('order_invoice_template_footer', ['order' => $order])->render();
		$mpdf->WriteHTML($footerHtml);

		// Output the PDF
		$publicPath = 'uploads/orders/';
		$fileName = 'invoice_' . $sanitizedOrderId . '.pdf';
		$filePath = storage_path('app/public/' . $publicPath . $fileName);

        // Check if the file already exists and delete it
        if (File::exists($filePath)) {
            File::delete($filePath);
        }

		if (!File::isDirectory($storage_path = storage_path('app/public/' . $publicPath))) {
			File::makeDirectory($storage_path, 0755, true);
		}

		$mpdf->Output($filePath, 'F');

        $fileUrl = asset('storage/' . $publicPath . $fileName);

        $update_order = OrderModel::where('id', $orderId)
        ->update([
            'order_invoice' => $fileUrl,
        ]);

        // Directly create an instance of SendWhatsAppUtility
        $whatsAppUtility = new sendWhatsAppUtility();

        if(!$is_edited)
        {
            $fileUrlWithTimestamp = $fileUrl . '?t=' . time();
            $templateParams = [
                'name' => 'ace_new_order_admin', // Replace with your WhatsApp template name
                'language' => ['code' => 'en'],
                'components' => [
                    [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'document',
                                'document' => [
                                    'link' =>  $fileUrlWithTimestamp, // Replace with the actual URL to the PDF document
                                    'filename' => $sanitizedOrderId.'.pdf' // Optional: Set a custom file name for the PDF document
                                ]
                            ]
                        ]
                    ],[
                        'type' => 'body',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $user->name,
                            ],
                            [
                                'type' => 'text',
                                'text' =>  substr($user->mobile, -10),
                            ],
                            [
                                'type' => 'text',
                                'text' => $order->order_id,
                            ],
                            [
                                'type' => 'text',
                                'text' => Carbon::now()->format('d-m-Y'),
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
                if($mobileNumber != '+919951263652')
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

            $templateParams = [
                'name' => 'ace_new_order_user', // Replace with your WhatsApp template name
                'language' => ['code' => 'en'],
                'components' => [
                    [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'document',
                                'document' => [
                                    'link' =>  $fileUrlWithTimestamp, // Replace with the actual URL to the PDF document
                                    'filename' => $sanitizedOrderId.'.pdf' // Optional: Set a custom file name for the PDF document
                                ]
                            ]
                        ]
                    ],[
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
                                'text' => Carbon::now()->format('d-m-Y'),
                            ],
                            [
                                'type' => 'text',
                                'text' => $order->amount,
                            ],
                        ],
                    ]
                ],
            ];

            $response = $whatsAppUtility->sendWhatsApp($user->mobile, $templateParams, '', 'User Order Invoice');
        }else{
            if($is_merged)
            {
                $fileUrlWithTimestamp = $fileUrl . '?t=' . time();
                $templateParams = [
                    'name' => 'ace_merged_order_admin', // Replace with your WhatsApp template name
                    'language' => ['code' => 'en'],
                    'components' => [
                        [
                            'type' => 'header',
                            'parameters' => [
                                [
                                    'type' => 'document',
                                    'document' => [
                                        'link' =>  $fileUrlWithTimestamp, // Replace with the actual URL to the PDF document
                                        'filename' => $sanitizedOrderId.'.pdf' // Optional: Set a custom file name for the PDF document
                                    ]
                                ]
                            ]
                        ],[
                            'type' => 'body',
                            'parameters' => [
                                [
                                    'type' => 'text',
                                    'text' => $user->name,
                                ],
                                [
                                    'type' => 'text',
                                    'text' =>  substr($user->mobile, -10),
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $merged_orders,
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $order->order_id,
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
                    if($mobileNumber != '+919951263652')
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

                $templateParams = [
                    'name' => 'ace_merged_order_user', // Replace with your WhatsApp template name
                    'language' => ['code' => 'en'],
                    'components' => [
                        [
                            'type' => 'header',
                            'parameters' => [
                                [
                                    'type' => 'document',
                                    'document' => [
                                        'link' =>  $fileUrlWithTimestamp, // Replace with the actual URL to the PDF document
                                        'filename' => $sanitizedOrderId.'.pdf' // Optional: Set a custom file name for the PDF document
                                    ]
                                ]
                            ]
                        ],[
                            'type' => 'body',
                            'parameters' => [
                                [
                                    'type' => 'text',
                                    'text' => $user->name,
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $merged_orders,
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $order->order_id,
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $order->amount,
                                ],
                            ],
                        ]
                    ],
                ];

                $response = $whatsAppUtility->sendWhatsApp($user->mobile, $templateParams, '', 'User Order Invoice');
            }
            else if($is_split)
            {
                $fileUrlWithTimestamp = $fileUrl . '?t=' . time();
                $templateParams = [
                    'name' => 'ace_split_order_admin', // Replace with your WhatsApp template name
                    'language' => ['code' => 'en'],
                    'components' => [
                        [
                            'type' => 'header',
                            'parameters' => [
                                [
                                    'type' => 'document',
                                    'document' => [
                                        'link' =>  $fileUrlWithTimestamp, // Replace with the actual URL to the PDF document
                                        'filename' => $sanitizedOrderId.'.pdf' // Optional: Set a custom file name for the PDF document
                                    ]
                                ]
                            ]
                        ],[
                            'type' => 'body',
                            'parameters' => [
                                [
                                    'type' => 'text',
                                    'text' => $user->name,
                                ],
                                [
                                    'type' => 'text',
                                    'text' =>  $old_order_id,
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $order->order_id,
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
                    if($mobileNumber != '+919951263652')
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

                $templateParams = [
                    'name' => 'ace_split_order_user', // Replace with your WhatsApp template name
                    'language' => ['code' => 'en'],
                    'components' => [
                        [
                            'type' => 'header',
                            'parameters' => [
                                [
                                    'type' => 'document',
                                    'document' => [
                                        'link' =>  $fileUrlWithTimestamp, // Replace with the actual URL to the PDF document
                                        'filename' => $sanitizedOrderId.'.pdf' // Optional: Set a custom file name for the PDF document
                                    ]
                                ]
                            ]
                        ],[
                            'type' => 'body',
                            'parameters' => [
                                [
                                    'type' => 'text',
                                    'text' => $user->name,
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $old_order_id,
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $order->order_id,
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $order->amount,
                                ],
                            ],
                        ]
                    ],
                ];

                $response = $whatsAppUtility->sendWhatsApp($user->mobile, $templateParams, '', 'User Order Invoice');
            }else{
                
                $fileUrlWithTimestamp = $fileUrl . '?t=' . time();
                $templateParams = [
                    'name' => 'ace_edit_order_admin', // Replace with your WhatsApp template name
                    'language' => ['code' => 'en'],
                    'components' => [
                        [
                            'type' => 'header',
                            'parameters' => [
                                [
                                    'type' => 'document',
                                    'document' => [
                                        'link' =>  $fileUrlWithTimestamp, // Replace with the actual URL to the PDF document
                                        'filename' => $sanitizedOrderId.'.pdf' // Optional: Set a custom file name for the PDF document
                                    ]
                                ]
                            ]
                        ],[
                            'type' => 'body',
                            'parameters' => [
                                [
                                    'type' => 'text',
                                    'text' => $user->name,
                                ],
                                [
                                    'type' => 'text',
                                    'text' =>  substr($user->mobile, -10),
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
                    if($mobileNumber != '+919951263652')
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

                $templateParams = [
                    'name' => 'ace_edit_order_user', // Replace with your WhatsApp template name
                    'language' => ['code' => 'en'],
                    'components' => [
                        [
                            'type' => 'header',
                            'parameters' => [
                                [
                                    'type' => 'document',
                                    'document' => [
                                        'link' =>  $fileUrlWithTimestamp, // Replace with the actual URL to the PDF document
                                        'filename' => $sanitizedOrderId.'.pdf' // Optional: Set a custom file name for the PDF document
                                    ]
                                ]
                            ]
                        ],[
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

                $response = $whatsAppUtility->sendWhatsApp($user->mobile, $templateParams, '', 'User Order Invoice');
            
            }
        }
    
        // // Assuming additional functionality such as WhatsApp integration etc.
        // return $mpdf->Output('invoice.pdf', 'I');
        return $fileUrl;
    }

    public function generateorderInvoice($orderId, array $options = [])
    {

        $get_user = Auth::User();

        $logged_user_id = $get_user->id;

        $is_edited = $options['is_edited'] ?? false;
        $is_merged = $options['is_merged'] ?? false;
        $merged_orders = $options['merged_orders'] ?? '';
        $is_split = $options['is_split'] ?? false;
        $old_order_id = $options['old_order_id'] ?? '';

        $order = OrderModel::select('user_id','name','mobile','order_id', 'amount', 'order_date','type', 'remarks')
                            ->where('id', $orderId)
                            ->first();

        if (!$order) return null;

        $user = User::select('name', 'mobile', 'email', 'address_line_1', 'address_line_2', 'gstin')
                    ->where('id', $order->user_id)
                    ->first();

        // --- Special handling for Quotation user (id: 226) ---
        if ($order->user_id == 226) {
            // Override user name and mobile with order's stored values
            $user->name   = $order->name ?? $user->name;
            $user->mobile = $order->mobile ?? $user->mobile;
        }

        $order_items = OrderItemsModel::with('product:product_code')
                        ->select('product_code', 'product_name', 'rate', 'quantity', 'total', 'remarks', 'size')
                        ->where('order_id', $orderId)->get();

        if (!$user || $order_items->isEmpty()) return null;

        $sanitizedOrderId = preg_replace('/[^A-Za-z0-9]+/', '-', trim($order->order_id));
        $sanitizedOrderId = trim($sanitizedOrderId, '-');
        $publicPath = 'uploads/orders/';
        $fileName = 'invoice_' . $sanitizedOrderId . '.pdf';
        $filePath = storage_path('app/public/' . $publicPath . $fileName);

        if (!File::isDirectory(dirname($filePath))) {
            File::makeDirectory(dirname($filePath), 0755, true);
        }
        if (File::exists($filePath)) {
            File::delete($filePath);
        }

        $mpdf = new \Mpdf\Mpdf();
        $mpdf->WriteHTML(view('order_invoice_template_header', ['user' => $user, 'order' => $order])->render());

        foreach ($order_items->chunk(10) as $chunk) {
            foreach ($chunk as $index => $item) {
                $mpdf->WriteHTML(view('order_invoice_template_items', compact('item', 'index'))->render());
            }
            flush();
        }

        $mpdf->WriteHTML(view('order_invoice_template_footer', ['order' => $order])->render());
        $mpdf->Output($filePath, 'F');

        $fileUrl = asset('storage/' . $publicPath . $fileName);

        OrderModel::where('id', $orderId)->update(['order_invoice' => $fileUrl]);

        $whatsAppUtility = new sendWhatsAppUtility();
        $fileUrlWithTimestamp = $fileUrl . '?t=' . time();
        $mobileNumbers = User::where('role', 'admin')->pluck('mobile')->toArray();

        if (!$is_edited) {
            $adminTemplate = 'ace_new_order_admin';
            $userTemplate = 'ace_new_order_user';
            $adminBodyParams = [
                $user->name,
                substr($user->mobile, -10),
                $order->order_id,
                now()->format('d-m-Y'),
                $order->amount,
            ];
            $userBodyParams = [
                $user->name,
                $order->order_id,
                now()->format('d-m-Y'),
                $order->amount,
            ];
        } elseif ($is_merged) {
            $adminTemplate = 'ace_merged_order_admin';
            $userTemplate = 'ace_merged_order_user';
            $adminBodyParams = [
                $user->name,
                substr($user->mobile, -10),
                $merged_orders,
                $order->order_id,
                $order->amount,
            ];
            $userBodyParams = [
                $user->name,
                $merged_orders,
                $order->order_id,
                $order->amount,
            ];
        } elseif ($is_split) {
            $adminTemplate = 'ace_split_order_admin';
            $userTemplate = 'ace_split_order_user';
            $adminBodyParams = [
                $user->name,
                $old_order_id,
                $order->order_id,
                $order->amount,
            ];
            $userBodyParams = [
                $user->name,
                $old_order_id,
                $order->order_id,
                $order->amount,
            ];
        } else {
            $adminTemplate = 'ace_edit_order_admin';
            $userTemplate = 'ace_edit_order_user';
            $adminBodyParams = [
                $user->name,
                substr($user->mobile, -10),
                $order->order_id,
                Carbon::parse($order->order_date)->format('d-m-Y'),
                $order->amount,
            ];
            $userBodyParams = [
                $user->name,
                $order->order_id,
                Carbon::parse($order->order_date)->format('d-m-Y'),
                $order->amount,
            ];
        }

        $sendDocParam = function($template, $params) use ($fileUrlWithTimestamp, $sanitizedOrderId) {
            return [
                'name' => $template,
                'language' => ['code' => 'en'],
                'components' => [
                    [
                        'type' => 'header',
                        'parameters' => [[
                            'type' => 'document',
                            'document' => [
                                'link' => $fileUrlWithTimestamp,
                                'filename' => $sanitizedOrderId.'.pdf'
                            ]
                        ]]
                    ],
                    [
                        'type' => 'body',
                        'parameters' => array_map(fn($text) => ['type' => 'text', 'text' => $text], $params)
                    ]
                ]
            ];
        };

        // -------------------- WhatsApp Dispatch Logic --------------------

        if ($logged_user_id != 75) {

            // 🔴 Special routing for user_id = 489
            if ($order->user_id == 489) {

                // Admin numbers (fixed)
                $adminMobiles = [
                    '+919966633307',
                    '+917506691380',
                ];

                foreach ($adminMobiles as $mobileNumber) {
                    $whatsAppUtility->sendWhatsApp(
                        $mobileNumber,
                        $sendDocParam($adminTemplate, $adminBodyParams),
                        '',
                        'Admin Order Invoice'
                    );
                }

                // User numbers (user + fixed numbers)
                $userMobiles = array_unique(array_filter([
                    $user->mobile,
                    '9833032423',
                    '9819427900',
                ]));

                foreach ($userMobiles as $mobileNumber) {
                    $whatsAppUtility->sendWhatsApp(
                        $mobileNumber,
                        $sendDocParam($userTemplate, $userBodyParams),
                        '',
                        'User Order Invoice'
                    );
                }

            } 
            // 🟢 Default behaviour (existing logic)
            else {

                foreach ($mobileNumbers as $mobileNumber) {
                    if ($mobileNumber != '+919951263652') {
                        $whatsAppUtility->sendWhatsApp(
                            $mobileNumber,
                            $sendDocParam($adminTemplate, $adminBodyParams),
                            '',
                            'Admin Order Invoice'
                        );
                    }
                }

                $whatsAppUtility->sendWhatsApp(
                    $user->mobile,
                    $sendDocParam($userTemplate, $userBodyParams),
                    '',
                    'User Order Invoice'
                );
            }

        } else {
            // Existing override
            $mobileNumber = '+917003541353';
            $whatsAppUtility->sendWhatsApp(
                $mobileNumber,
                $sendDocParam($adminTemplate, $adminBodyParams),
                '',
                'Admin Order Invoice'
            );
        }

        // if($logged_user_id != 75){
        //     foreach ($mobileNumbers as $mobileNumber) {
        //         if ($mobileNumber != '+919951263652') {
        //             $whatsAppUtility->sendWhatsApp($mobileNumber, $sendDocParam($adminTemplate, $adminBodyParams), '', 'Admin Order Invoice');
        //         }
        //     }

        //     $whatsAppUtility->sendWhatsApp($user->mobile, $sendDocParam($userTemplate, $userBodyParams), '', 'User Order Invoice');
        // }else{
        //     $mobileNumber = '+917003541353';
        //     $whatsAppUtility->sendWhatsApp($mobileNumber, $sendDocParam($adminTemplate, $adminBodyParams), '', 'Admin Order Invoice');
        // }

        return $fileUrl;
    }

    public function generatePackingSlip($orderId, $is_edited = false, $is_download = false)
    {
        $get_user = Auth::User();

        $logged_user_id = $get_user->id;

        $order = OrderModel::select('user_id','order_id', 'amount', 'order_date','type', 'remarks')
                            ->where('id', $orderId)
                            ->first();

        $get_user = $order->user_id;
        
        $user = User::select('name', 'mobile', 'email', 'address_line_1', 'address_line_2', 'gstin')
                    ->where('id', $get_user)
                    ->first();

        $order_items = OrderItemsModel::with('product:product_code')
                                    ->select('product_code', 'product_name', 'rate', 'quantity', 'total', 'remarks', 'size')
                                    ->where('order_id', $orderId)
                                    ->get();

        // Get all product_codes used in this order
        $productCodes = $order_items->pluck('product_code')->unique()->toArray();

        // Fetch current stock for all product codes in one query
        $stockMap = StockOrderItemsModel::select(
                'product_code',
                DB::raw("SUM(CASE WHEN type = 'IN' THEN quantity ELSE 0 END) AS total_in"),
                DB::raw("SUM(CASE WHEN type = 'OUT' THEN quantity ELSE 0 END) AS total_out")
            )
            ->whereIn('product_code', $productCodes)
            ->groupBy('product_code')
            ->get()
            ->mapWithKeys(function ($row) {
                return [
                    $row->product_code => ($row->total_in - $row->total_out),
                ];
            });

        // Append `current_stock` to each order item
        foreach ($order_items as $item) {
            $item->current_stock = $stockMap[$item->product_code] ?? 0;
        }

        // Get the total pending quantity of each product (if any orders are pending)
        $pendingQuantities = OrderItemsModel::select('t_order_items.product_code', DB::raw('SUM(t_order_items.quantity) as total_pending'))
            ->join('t_orders', 't_order_items.order_id', '=', 't_orders.id') // Correct join between t_order_items and t_orders
            ->where('t_orders.status', 'pending') // Filter for orders with status 'pending'
            ->groupBy('t_order_items.product_code') // Group by product_code
            ->get()
            ->keyBy('product_code') // Key by product_code for easier lookup
            ->map(function ($item) {
                return $item->total_pending; // Return the total_pending value
            }
        );


        // Append pending_qty and balance_stock to each item
        foreach ($order_items as $item) {
            $pending_qty = $pendingQuantities[$item->product_code] ?? 0; // If not set, default to 0
            $item->pending_qty = $pending_qty;
            $item->balance_stock = $item->current_stock - $pending_qty; // Calculate balance stock
        }


        $mobileNumbers = User::where('role', 'admin')->pluck('mobile')->toArray();
        

        if (!$user || !$order || $order_items->isEmpty()) {
            return response()->json(['error' => 'Sorry, required data are not available!'], 500);
        }

        $sanitizedUserName = preg_replace('/[^A-Za-z0-9]+/', '-', trim($user->name));
        $sanitizedUserId = trim($sanitizedUserName, '-');

        $sanitizedOrderId = preg_replace('/[^A-Za-z0-9]+/', '-', trim($order->order_id));
        $sanitizedOrderId = trim($sanitizedOrderId, '-');

		//die($sanitizedOrderId);
		
        $data = [
            'user' => $user,
            'order' => $order,
            'order_items' => $order_items,
        ];
		$mpdf = new Mpdf();

		// Render the header
		$headerHtml = view('packing_slip_template_header', ['user' => $user, 'order' => $order])->render();
		$mpdf->WriteHTML($headerHtml);

		$chunkSize = 10;
		$orderItems = collect($order_items)->chunk($chunkSize);

		foreach ($orderItems as $chunk) {
			foreach ($chunk as $index => $item) {
				// Render each item row individually
				$htmlChunk = view('packing_slip_template_items', compact('item', 'index'))->render();
				$mpdf->WriteHTML($htmlChunk);
			}
			//ob_flush();
			flush();
		}

		// Render the footer
		$footerHtml = view('packing_slip_template_footer', ['order' => $order])->render();
		$mpdf->WriteHTML($footerHtml);

		// Output the PDF
		$publicPath = 'uploads/packing_slip/';
		$fileName = 'ps_' . $sanitizedUserId . '_' . $sanitizedOrderId . '.pdf';
		$filePath = storage_path('app/public/' . $publicPath . $fileName);

        // Check if the file already exists and delete it
        if (File::exists($filePath)) {
            File::delete($filePath);
        }

		if (!File::isDirectory($storage_path = storage_path('app/public/' . $publicPath))) {
			File::makeDirectory($storage_path, 0755, true);
		}

		$mpdf->Output($filePath, 'F');

        $fileUrl = asset('storage/' . $publicPath . $fileName);

        $update_order = OrderModel::where('id', $orderId)
        ->update([
            'packing_slip' => $fileUrl,
        ]);

        if($is_download)
        {
            // If not downloading, return the file URL
            $fileUrlWithTimestamp = $fileUrl . '?t=' . time();
            return $fileUrlWithTimestamp;
        }
        // Directly create an instance of SendWhatsAppUtility
        $whatsAppUtility = new sendWhatsAppUtility();

        if(!$is_edited)
        {
            $fileUrlWithTimestamp = $fileUrl . '?t=' . time();
            $templateParams = [
                'name' => 'ace_new_order_admin', // Replace with your WhatsApp template name
                'language' => ['code' => 'en'],
                'components' => [
                    [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'document',
                                'document' => [
                                    'link' =>  $fileUrlWithTimestamp, // Replace with the actual URL to the PDF document
                                    'filename' => $sanitizedUserId.'.pdf' // Optional: Set a custom file name for the PDF document
                                ]
                            ]
                        ]
                    ],[
                        'type' => 'body',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $user->name,
                            ],
                            [
                                'type' => 'text',
                                'text' =>  substr($user->mobile, -10),
                            ],
                            [
                                'type' => 'text',
                                'text' => $order->order_id,
                            ],
                            [
                                'type' => 'text',
                                'text' => Carbon::now()->format('d-m-Y'),
                            ],
                            [
                                'type' => 'text',
                                'text' => '0',
                            ],
                        ],
                    ]
                ],
            ];
            if($logged_user_id != 75){
                foreach ($mobileNumbers as $mobileNumber) 
                {
                    if($mobileNumber == '+919951263652')
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
            }
        }else{
            $fileUrlWithTimestamp = $fileUrl . '?t=' . time();
            $templateParams = [
                'name' => 'ace_edit_order_admin', // Replace with your WhatsApp template name
                'language' => ['code' => 'en'],
                'components' => [
                    [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'document',
                                'document' => [
                                    'link' =>  $fileUrlWithTimestamp, // Replace with the actual URL to the PDF document
                                    'filename' => $sanitizedUserId.'.pdf' // Optional: Set a custom file name for the PDF document
                                ]
                            ]
                        ]
                    ],[
                        'type' => 'body',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $user->name,
                            ],
                            [
                                'type' => 'text',
                                'text' =>  substr($user->mobile, -10),
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
                                'text' => '0',
                            ],
                        ],
                    ]
                ],
            ];

            if($logged_user_id != 75){
                foreach ($mobileNumbers as $mobileNumber) 
                {
                    if($mobileNumber == '+919951263652')
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
            }
        }
    
        // return $mpdf->Output('invoice.pdf', 'I');
        return $fileUrl;
    }

    public function generatePackingSlipsForAllOrders()
    {
        // Fetch orders with 'pending' or 'completed' status
        $orders = OrderModel::whereIn('status', ['pending', 'completed'])  // Adjust the status values as needed
                            ->whereNull('packing_slip')
                            ->orderBy('id', 'desc') 
                            ->get();

        // Loop through each order and generate the packing slip
        foreach ($orders as $order) {
            // Call the generatePackingSlip function for each order
            $this->generatePackingSlip($order->id);
        }

        return response()->json(['message' => 'Packing slips generated for all pending and completed orders.'], 200);
    }

    public function generateInvoice($invoiceId)
    {

        $invoice_user = InvoiceModel::with('user:id,name,mobile,email,address_line_1,address_line_2,gstin')
                            ->select('order_id', 'invoice_number', 'date', 'amount', 'type', 'user_id')
                            ->where('id', $invoiceId)
                            ->first();


        $invoice_items = InvoiceItemsModel::select('product_code', 'product_name', 'rate', 'quantity', 'total')
                                    ->where('invoice_id', $invoiceId)
                                    ->get();    

        if (!isset($invoice_user) || !isset($invoice_user->user) || $invoice_items->isEmpty()) {
            return response()->json(['error' => 'Sorry, required data are not available!'], 500);
        }

        $sanitizedInvoiceNumber = preg_replace('/[^A-Za-z0-9]+/', '-', trim($invoice_user->invoice_number));
        $sanitizedInvoiceNumber = trim($sanitizedInvoiceNumber, '-');

        $data = [
            'user' => $invoice_user->user,
            'invoice' => $invoice_user,
            'invoice_items' => $invoice_items,
        ];

        $html = view('invoice_template', $data)->render();

        $mpdf = new Mpdf();
        $mpdf->WriteHTML($html);

        $publicPath = 'uploads/invoices/';
        $fileName = 'invoice_' . $sanitizedInvoiceNumber . '.pdf';
        $filePath = storage_path('app/public/' . $publicPath . $fileName);

        if (!File::isDirectory($storage_path = storage_path('app/public/' . $publicPath))) {
            File::makeDirectory($storage_path, 0755, true);
        }

        $mpdf->Output($filePath, 'F');

        $fileUrl = asset('storage/' . $publicPath . $fileName);

        $update_order = InvoiceModel::where('id', $invoiceId)
        ->update([
            'invoice_file' => $fileUrl,
        ]);

        $templateParams = [
            'name' => 'ace_new_order_user', // Replace with your WhatsApp template name
            'language' => ['code' => 'en'],
            'components' => [
                [
                    'type' => 'header',
                    'parameters' => [
                        [
                            'type' => 'document',
                            'document' => [
                                'link' =>  $fileUrl, // Replace with the actual URL to the PDF document
                                'filename' => $sanitizedInvoiceNumber.'.pdf' // Optional: Set a custom file name for the PDF document
                            ]
                        ]
                    ]
                ],[
                    'type' => 'body',
                    'parameters' => [
                        [
                            'type' => 'text',
                            'text' => $invoice_user->user->name,
                        ],
                        [
                            'type' => 'text',
                            'text' => $invoice_user->invoice_number,
                        ],
                        [
                            'type' => 'text',
                            'text' => Carbon::now()->format('d-m-Y'),
                        ],
                        [
                            'type' => 'text',
                            'text' => $invoice_user->amount,
                        ],
                    ],
                ]
            ],
        ];
        
        // Directly create an instance of SendWhatsAppUtility
        $whatsAppUtility = new sendWhatsAppUtility();
        
        $response = $whatsAppUtility->sendWhatsApp($user->mobile, $templateParams, '', 'User Invoice');

        return $fileUrl;
    }

    public function price_list(Request $request)
    {
        // Inputs
        $categoryParam = trim((string) $request->input('category', '')); // e.g., "3,7,12"
        $search_text   = $request->input('search_text');
        $type          = $request->input('type');
        // Capture the series parameter
        $series = $request->input('series');

        // Resolve category names from comma-separated IDs (also supports names if provided)
        $categoryNames = collect();
        if ($categoryParam !== '') {
            $tokens = collect(explode(',', $categoryParam))
                ->map(fn ($v) => trim($v))
                ->filter();

            $ids   = $tokens->filter(fn ($v) => ctype_digit($v))->map(fn ($v) => (int) $v)->values();
            $names = $tokens->filter(fn ($v) => !ctype_digit($v))->values(); // if caller passed names

            if ($ids->isNotEmpty()) {
                $categoryNames = $categoryNames->merge(
                    CategoryModel::whereIn('id', $ids)->pluck('name')
                );
            }
            if ($names->isNotEmpty()) {
                // Normalize to the canonical names that exist in categories table
                $categoryNames = $categoryNames->merge(
                    CategoryModel::whereIn('name', $names)->pluck('name')
                );
            }

            $categoryNames = $categoryNames->unique()->values();

            // If user asked for categories but none resolved → no results
            if ($categoryNames->isEmpty()) {
                return response()->json(['message' => 'No products found.'], 200);
            }
        }

        // Auth user
        $get_user = Auth::user();

        // Determine display name (kept same logic)
        if ($get_user && $get_user->role === 'user') {
            $user_name = $get_user->name;
        } else {
            $request->validate([
                'id' => 'required|integer|exists:users,id'
            ]);
            $id        = (int) $request->input('id');
            $user_name = User::where('id', $id)->value('name');
        }

        // Price column (kept as your original default)
        $price_column = 'outstation_gst';

        // Base query
        $query = ProductModel::select(
                'product_name',
                'product_code',
                'brand',
                DB::raw("$price_column as price"),
                'product_image'
            )
            ->whereNotNull('product_image')
            ->where('product_image', '!=', '')
            ->orderBy('id');
        // Apply series filter if 'series' value is 'MP'
        if ($series === 'MP') {
            $query->where('product_code', 'like', 'MP%');
        }

        // Category filter (by names, since products.category holds the name)
        if ($categoryNames->isNotEmpty()) {
            $query->whereIn('category', $categoryNames->all());
        }

        // Search filter
        if (!empty($search_text)) {
            $searchWords = preg_split('/[\s\.\-]+/', mb_strtolower($search_text)) ?: [];
            $query->where(function ($q) use ($searchWords) {
                foreach ($searchWords as $word) {
                    $q->where(function ($subQ) use ($word) {
                        $subQ->orWhereRaw('LOWER(product_name) LIKE ?', ["%{$word}%"])
                            ->orWhereRaw('LOWER(product_code) LIKE ?', ["%{$word}%"]);
                    });
                }
            });
        }

        // Fetch (cap results)
        $get_product_details = $query->take(1000)->get();

        if ($get_product_details->isEmpty()) {
            return response()->json(['message' => 'No products found.'], 200);
        }

        // Choose banner name based on series
        $bannerName = ($series === 'MP') ? 'S2' : 'S1';

        // Choose view (with/without price)
        if ($get_user && $get_user->role === 'user') {
            $html = view('price_list_user', compact('get_product_details', 'user_name', 'bannerName'))->render();
        } else {
            if ($type === 'without_price') {
                $html = view('price_list_user', compact('get_product_details', 'user_name', 'bannerName'))->render();
            } else {
                $html = view('price_list', compact('get_product_details', 'user_name', 'bannerName'))->render();
            }
        }

        // Generate PDF
        $mpdf = new \Mpdf\Mpdf();
        $mpdf->WriteHTML($html);

        // Storage pathing
        $publicPath = 'uploads/price_list/';
        $timestamp  = now()->format('Ymd_His');

        // Build filename:
        // - If search text present → use that
        // - Else if categories present → join their names (limit length)
        // - Else default timestamped
        $fileName = "price_list_{$timestamp}.pdf";

        if (!empty($search_text)) {
            $fileName = preg_replace('/[^\w\-]+/u', '_', trim($search_text)) . '.pdf';
        } elseif ($categoryNames->isNotEmpty()) {
            $joined = $categoryNames->take(5)->implode('_'); // avoid extremely long file names
            if ($categoryNames->count() > 5) {
                $joined .= '_more';
            }
            $fileName = preg_replace('/[^\w\-]+/u', '_', $joined) . '.pdf';
        }

        $dirPath  = storage_path('app/public/' . $publicPath);
        $filePath = $dirPath . $fileName;

        if (!\Illuminate\Support\Facades\File::isDirectory($dirPath)) {
            \Illuminate\Support\Facades\File::makeDirectory($dirPath, 0755, true);
        }

        // Save PDF
        $mpdf->Output($filePath, 'F');

        // Public URL
        $fileUrl = asset('storage/' . $publicPath . $fileName);

        return $fileUrl;
    }

}
