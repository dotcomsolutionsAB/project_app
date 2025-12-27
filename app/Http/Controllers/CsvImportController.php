<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\ProductModel;
use App\Models\User;
use League\Csv\Reader;
use League\Csv\Statement;
use Hash;
use App\Models\CategoryModel;
use App\Models\SubCategoryModel;
use Illuminate\Support\Facades\Validator;
use App\Models\GodownModel;

class CsvImportController extends Controller
{
    //
    public function importProduct()
    {
        // URL of the CSV file from Google Sheets
        $get_product_csv_url = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vQaU_DTPcjgHGqqE_THQNQuisDEsIXH2PJwGaNOGd5ND5F7mVXpgS5KJ7lv4pgRyb9vUtGk_GTPSTDo/pub?gid=559891556&single=true&output=csv';

        // Fetch the CSV content using file_get_contents
        $csvContent_product = file_get_contents($get_product_csv_url);

        // Fetch and parse the CSV
        $csv_product = Reader::createFromString($csvContent_product);

        $csv_product->setHeaderOffset(0); // Set the header offset
        

        $records_csv = (new Statement())->process($csv_product);

        $product_insert_response = null;
        $product_update_response = null;

        $index = 1; // Start from 1

        // Iterate through each record and create or update the product
        foreach ($records_csv as $record_csv) {

            // SKIP MP series products
            // if (isset($record_csv['Product Code']) && preg_match('/^MP\d+/i', $record_csv['Product Code'])) {
            //     continue; // jump to next row
            // }

            // Check if 'Yet to Launch' is 1, delete the product if it exists
            if ($record_csv['Delete'] == 'TRUE') {
                $product_csv = ProductModel::where('product_code', $record_csv['Product Code'])->first();
                if ($product_csv) {
                    $product_csv->delete();
                }
                continue; // Skip to the next record
            }
            
            $product_csv = ProductModel::where('product_code', $record_csv['Product Code'])->first();

            $purchasePrice_product =
            ($record_csv['GST Base Price - temp'] ?? '') !== ''
                ? $record_csv['GST Base Price - temp']
                : (
                    ($record_csv['MP Purchase Price'] ?? '') !== ''
                        ? $record_csv['MP Purchase Price']
                        : 0
                );
            $basicPrice_product = $record_csv['Basic Price'] !== '' ? $record_csv['Basic Price'] : 0;
            $gstPrice_prduct = $record_csv['GST Price'] !== '' ? $record_csv['GST Price'] : 0;
			$basicPrice_product_special = $record_csv['Special Basic Price'] !== '' ? $record_csv['Special Basic Price'] : 0;
            $gstPrice_prduct_special = $record_csv['Special GST Price'] !== '' ? $record_csv['Special GST Price'] : 0;
            $basicPrice_product_outstation = $record_csv['Outstation Basic Price'] !== '' ? $record_csv['Outstation Basic Price'] : 0;
            $gstPrice_prduct_outstation = $record_csv['Outstation GST Price'] !== '' ? $record_csv['Outstation GST Price'] : 0;
            $aakhambati_gst_outstation = $record_csv['AA Khambati Price'] !== '' ? $record_csv['AA Khambati Price'] : 0;
            $guest_price = $record_csv['Guest Price'] !== '' ? $record_csv['Guest Price'] : 0;
            $mp_price = $record_csv['MP Purchase Price'] !== '' ? $record_csv['MP Purchase Price'] : 0;
            $re_order_level = $record_csv['RE ORDER LEVEL'] !== '' ? $record_csv['RE ORDER LEVEL'] : 0;
            $filename = $record_csv['Product Code'];

            $category = $record_csv['Category'];
            $sub_category = $record_csv['Sub Category'];
            $brand = $record_csv['Brand'] !== '' ? $record_csv['Brand'] : null;

            // Define the product image paths
            $productImagePath = "/storage/uploads/products/{$filename}.jpg";
            $productImagePathPdf = "/storage/uploads/products_pdf/{$filename}.jpg";
            $product_imagePath_for_not_available = "/storage/uploads/products/placeholder.jpg";
            
            // Check if the image exists in the product path
            if (file_exists(public_path($productImagePath))) {
                // Image exists, keep the productImagePath as is
                $productImagePath = $productImagePath;
            } elseif (file_exists(public_path($productImagePathPdf))) {
                // Image doesn't exist in the product path, check in the PDF path
                $productImagePath = $productImagePathPdf;
            } else {
                // If neither image exists, use the placeholder
                $productImagePath = $product_imagePath_for_not_available;
            }

            // Handle extra images
            $extraImages = [];
            $extraImagePath = public_path("/storage/uploads/extra");
            $i = 1;

            // Check for extra images in the format PRODUCT_CODE-1, PRODUCT_CODE-2, etc.
            while (file_exists("{$extraImagePath}/{$filename}-{$i}.jpg")) {
                $extraImages[] = "/storage/uploads/extra/{$filename}-{$i}.jpg";
                $i++;
            }

            // Convert extra images array to a comma-separated string
            $extraImagesCsv = implode(',', $extraImages);

            if ($product_csv) 
            {
                // If product exists, update it
                $product_update_response = $product_csv->update([
                    'product_code' => $record_csv['Product Code'],
                    'product_name' => $record_csv['Product Name'],
                    'name_in_hindi' => $record_csv['Name in Hindi'],
                    'name_in_telugu' => $record_csv['Name in Telugu'],
                    'brand' => $brand,
                    'category' => $record_csv['Category'],
                    'sub_category' => $record_csv['Sub Category'],
                    'type' => $record_csv['Type'],
                    'hsn' => $record_csv['HSN'],
                    'tax' => $record_csv['Tax'],
                    'machine_part_no' => $record_csv['Machine Part No'],
                    'purchase' => $purchasePrice_product, // Ensure this is a valid number
                    'basic' => $basicPrice_product, // Ensure this is a valid number
                    'gst' => $gstPrice_prduct,     // Ensure this is a valid number
                    'special_basic' => $basicPrice_product_special,     // Ensure this is a valid number
                    'special_gst' => $gstPrice_prduct_special,     // Ensure this is a valid number
                    'outstation_basic' => $basicPrice_product_outstation,     // Ensure this is a valid number
                    'outstation_gst' => $gstPrice_prduct_outstation,     // Ensure this is a valid number
                    'aakhambati_gst' => $aakhambati_gst_outstation,     // Ensure this is a valid number
                    'guest_price' => $guest_price,     // Ensure this is a valid number
                    'mp_price' => $mp_price,     // Ensure this is a valid number
                    're_order_level' => $re_order_level,     // Ensure this is a valid number
                    'out_of_stock' => $record_csv['Out of Stock'] === 'TRUE' ? 1 : 0,
                    'yet_to_launch' => $record_csv['Yet to Launch'] === 'TRUE' ? 1 : 0,
                    // 'product_image' => null, // Set this if you have the image URL or path
                    'product_image' => $productImagePath,
                    'extra_images' => $extraImagesCsv, // Set the extra images
                    'video_link' => $record_csv['YouTube Link'],
                    'size' => $record_csv['Sizes'],
                    'order_by'       => $index,
                ]);
            } 
            else 
            {
                // If product does not exist, create a new one
                $product_insert_response = ProductModel::create([
                    'product_code' => $record_csv['Product Code'],
                    'product_name' => $record_csv['Product Name'],
                    'name_in_hindi' => $record_csv['Name in Hindi'],
                    'name_in_telugu' => $record_csv['Name in Telugu'],
                    'brand' => $brand,
                    'category' => $record_csv['Category'],
                    'sub_category' => $record_csv['Sub Category'],
                    'type' => $record_csv['Type'],
                    'machine_part_no' => $record_csv['Machine Part No'],
                    'purchase' => $purchasePrice_product, // Ensure this is a valid number
                    'basic' => $basicPrice_product, // Ensure this is a valid number
                    'gst' => $gstPrice_prduct,     // Ensure this is a valid number
					'special_basic' => $basicPrice_product_special,     // Ensure this is a valid number
                    'special_gst' => $gstPrice_prduct_special,     // Ensure this is a valid number
                    'outstation_basic' => $basicPrice_product_outstation,     // Ensure this is a valid number
                    'outstation_gst' => $gstPrice_prduct_outstation,     // Ensure this is a valid number
                    'guest_price' => $guest_price,     // Ensure this is a valid number
                    're_order_level' => $re_order_level,     // Ensure this is a valid number
                    'out_of_stock' => $record_csv['Out of Stock'] === 'TRUE' ? 1 : 0,
                    'yet_to_launch' => $record_csv['Yet to Launch'] === 'TRUE' ? 1 : 0,
                    // 'product_image' => null, // Set this if you have the image URL or path
                    'product_image' => $productImagePath,
                    'size' => $record_csv['Sizes'],
                    'order_by'       => $index,
                ]);
                
            }
            $index++;
        }   
        if ($product_update_response == 1 || isset($product_insert_response)) {
            return response()->json(['message' => 'Products imported successfully'], 200);
        }
        else {
            return response()->json(['message' => 'Sorry, failed to imported successfully'], 400);
        }
    }

    public function importUser()
    {
        // URL of the CSV file from Google Sheets
        $get_product_user_url = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vQaU_DTPcjgHGqqE_THQNQuisDEsIXH2PJwGaNOGd5ND5F7mVXpgS5KJ7lv4pgRyb9vUtGk_GTPSTDo/pub?gid=1797389278&single=true&output=csv';

        // Fetch the CSV content using file_get_contents
        $csvContent_user = file_get_contents($get_product_user_url);

        // Fetch and parse the CSV
        $csv_user = Reader::createFromString($csvContent_user);

        $csv_user->setHeaderOffset(0); // Set the header offset

        $records_user = (new Statement())->process($csv_user);

        $get_insert_response = null;
        $get_update_response = null;

        // Iterate through each record and create or update the product
        foreach ($records_user as $record_user) {

            if (strlen($record_user['Mobile']) == 10) {
                // If it's 10 digits, add '+91' prefix
                $mobile = '+91' . $record_user['Mobile'];
            } elseif (strlen($record_user['Mobile']) == 12) {
                // If it's 12 digits, add '+' prefix
                $mobile = '+' . $record_user['Mobile'];
            } else {
                $mobile = $record_user['Mobile'];
            }

            $user_csv = User::where('mobile', $mobile)->first();

            // Handle potential empty values for email, pincode, and markup
            $email_user = !empty($record_user['Email']) ? $record_user['Email'] : null;
            $pincode_user = $record_user['Pincode'] !== '' ? $record_user['Pincode'] : 0;
            $markup_user = $record_user['Type'] !== '' ? strtolower($record_user['Type']) : 'normal';

            if ($user_csv) 
            {
                // If user exists, update it
                $get_update_response = $user_csv->update([
                    'name' => $record_user['Name'],
                    'email' => $email_user,
                    'name_in_hindi' => $record_user['Hindi'],
                    'name_in_telugu' => $record_user['Telegu'],
                    'address_line_1' => $record_user['Address Line 1'],
                    'address_line_2' => $record_user['Address Line 2'],
                    'city' => $record_user['City'],
                    'pincode' => $pincode_user,// Ensure this is a valid number
                    'gstin' => $record_user['GSTIN'],
                    'state' => $record_user['State'],
                    'country' => $record_user['Country'],
                    'purchase_limit' => isset($record_user['Purchase Limit']) ? $record_user['Purchase Limit'] : '0',  // Default to '0' if blank,
                    'zoho_customer_id' => $record_user['Zoho Customer ID'],
                ]);
            } 
            else 
            {
                // If user does not exist, create a new one
                if (!empty($record_user['Name'])) {
                    $get_insert_response = User::create([
                        'mobile' => $mobile,
                        'name' => $record_user['Name'],
                        'email' => $email_user,
                        'password' => bcrypt($mobile),
                        'name_in_hindi' => $record_user['Hindi'],
                        'name_in_telugu' => $record_user['Telegu'],
                        'address_line_1' => $record_user['Address Line 1'],
                        'address_line_2' => $record_user['Address Line 2'],
                        'is_verified' => '1',
                        'city' => $record_user['City'],
                        'pincode' => $pincode_user, // Ensure this is a valid number
                        'gstin' => $record_user['GSTIN'],
                        'state' => $record_user['State'],
                        'country' => $record_user['Country'],
                        'type' => $markup_user, // Ensure this is a valid number
                        'purchase_limit' => isset($record_user['Purchase Limit']) ? $record_user['Purchase Limit'] : '0',  // Default to '0' if blank,
                        'zoho_customer_id' => $record_user['Zoho Customer ID'],
                    ]);
                }
                
            }
        }   

        if ($get_update_response == 1 || isset($get_insert_response)) {
            return response()->json(['message' => 'Users imported successfully'], 200);
        }
        else {
            return response()->json(['message' => 'Sorry, failed to imported successfully'], 400);
        }
    }

    public function importCategory()
    {
        // URL of the CSV file from Google Sheets
        $get_product_category_url = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vQaU_DTPcjgHGqqE_THQNQuisDEsIXH2PJwGaNOGd5ND5F7mVXpgS5KJ7lv4pgRyb9vUtGk_GTPSTDo/pub?gid=736987385&single=true&output=csv';

        // Fetch the CSV content using file_get_contents
        $csvContent_category = file_get_contents($get_product_category_url);

        // Fetch and parse the CSV
        $csv_category = Reader::createFromString($csvContent_category);

        $csv_category->setHeaderOffset(0); // Set the header offset
        

        $records_csv = (new Statement())->process($csv_category);

        $get_insert_response = null;
        $get_update_response = null;

        $index = 1; // Start from 1

        // Iterate through each record and create or update the product
        foreach ($records_csv as $record_csv) {

            $category_csv = categoryModel::where('name', $record_csv['name'])->first();
			
			//die(json_encode($record_csv));

            $category = $record_csv['name'];
            $category_image = '';

            $categoryNameSanitized = strtolower($category);

            // Replace &, /, -, and spaces with underscore
            $categoryNameSanitized = preg_replace('/[&\/\-\s]+/', '_', $categoryNameSanitized);

            // Remove any remaining special characters
            $categoryNameSanitized = preg_replace('/[^a-z0-9_]/', '', $categoryNameSanitized);

            // Remove duplicate underscores
            $categoryNameSanitized = preg_replace('/_+/', '_', $categoryNameSanitized);

            // Trim underscores from start and end
            $categoryNameSanitized = trim($categoryNameSanitized, '_');

            $imagePath = "/storage/uploads/category/{$categoryNameSanitized}.jpg";
            $category_imagePath_for_not_avaliable = "/storage/uploads/category/placeholder.jpg";

            if (file_exists(public_path($imagePath))) 
            {
                $category_image = $imagePath;
            }
            else 
            {
                $category_image = $category_imagePath_for_not_avaliable;
            }

            if ($category_csv) 
            {
                // If category exists, update it
                $get_update_response = $category_csv->update([
                    'name'           => $record_csv['name'],
                    'image'          => $category_image,
                    'name_in_hindi'  => $record_csv['name_in_hindi'],
                    'name_in_telugu' => $record_csv['name_in_telugu'],
                    'order_by'       => $index,
                ]);
            } 
            else 
            {
                // If user does not exist, create a new one
                $get_insert_response = categoryModel::create([
                    'name'           => $record_csv['name'],
                    'image'          => $category_image,
                    'name_in_hindi'  => $record_csv['name_in_hindi'],
                    'name_in_telugu' => $record_csv['name_in_telugu'],
                    'order_by'       => $index,
                ]);
            }
            $index++;
        }   

        if ($get_update_response == 1 || isset($get_insert_response)) {
            return response()->json(['message' => 'Categories imported successfully'], 200);
        }
        else {
            return response()->json(['message' => 'Sorry, failed to import data'], 400);
        }
    }

    public function importCsv_godown()
    {
        try {

            // Load the CSV file
            $get_product_category_url = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vQaU_DTPcjgHGqqE_THQNQuisDEsIXH2PJwGaNOGd5ND5F7mVXpgS5KJ7lv4pgRyb9vUtGk_GTPSTDo/pub?gid=799829324&single=true&output=csv';

            // Fetch the CSV content using file_get_contents
            $csvContent_category = file_get_contents($get_product_category_url);

            // Fetch and parse the CSV
            $csv_category = Reader::createFromString($csvContent_category);

            $csv_category->setHeaderOffset(0); // Set the header offset
            

            $records_csv = (new Statement())->process($csv_category);

            // Extract the header and map it to DB columns
            // $header = array_map('trim', $csvData[0]);
            // $header = array_flip($header);

            // if (!isset($header['Name'], $header['Description'])) {
            //     return response()->json(['message' => 'CSV file does not contain required columns: Name, Description.'], 422);
            // }

            // Prepare data for bulk insert
            $data = [];
            foreach ($records_csv as $record) {
                if (isset($record['Name'], $record['Description'])) {
                    $data[] = [
                        'name' => trim($record['Name']),
                        'description' => trim($record['Description']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (empty($data)) {
                return response()->json(['message' => 'No valid data found in the CSV file.'], 422);
            }

            // Use chunking to insert data in batches using Eloquent
            $chunkSize = 1000; // Number of records per batch
            $chunks = array_chunk($data, $chunkSize);

            // Use the truncate method from the model's table
            GodownModel::truncate();

            foreach ($chunks as $chunk) {
                GodownModel::insert($chunk);
            }

            return response()->json([
                'message' => 'CSV data imported successfully.',
                'total_records' => count($data),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred during import.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}