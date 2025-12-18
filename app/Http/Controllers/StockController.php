<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Mpdf\Mpdf;
use App\Models\StockOrderItemsModel;
use App\Models\GodownModel;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

ini_set('memory_limit', '512M'); // Adjust as needed
set_time_limit(300); // Increase timeout to 5 minutes or as needed

class StockController extends Controller
{
    public function generateStockReport(Request $request)
    {

        // If 'type' is required, you can also validate the presence of the type
        $request->validate([
            'type' => 'required|in:with_value,without_value',  // Only allow 'with_value' or 'without_value'
        ]);

        // $type = $request->type;
        // 🔹 Validate 'type' to ensure it is either passed in the request or defaults to 'without_value'
        $type = $request->input('type', 'without_value'); // Set default to 'without_value' if not passed

        // Capture the series parameter
        $series = $request->input('series', null); // Default to null if not provided

        // // Apply series filter if 'series' value is 'MP'
        // if ($series === 'MP') {
        //     // Fetch only products with product_code starting with 'MP'
        //     $stockData = $stockData->filter(function ($item) {
        //         return strpos($item->product_code, 'MP') === 0;
        //     });
        // }

        // // Fetch stock data with category, type, and purchase price using relationships
        // $stockData = StockOrderItemsModel::with(['stock_product:id,product_code,product_name,category,type,purchase', 'godown:id,name'])
        //     ->get()
        //     ->sortBy(function ($item) {
        //         // Check if stock_product is not null
        //         if ($item->stock_product) {
        //             $typeOrder = ['MACHINE' => 1, 'ACCESSORIES' => 2, 'SPARE' => 3]; // Define type priority
        //             $typeRank = $typeOrder[$item->stock_product->type] ?? 4; // Default rank for unknown types
        //             return [$typeRank, $item->stock_product->category]; // Sort first by type, then by category
        //         }
        //         // Return a default value if stock_product is null
        //         return [4, 'Unknown']; // You can adjust this based on your needs
        //     });
        
        // Initialize the stock data query
            $stockDataQuery = StockOrderItemsModel::with(['stock_product:id,product_code,product_name,category,type,purchase', 'godown:id,name']);

        if ($series === 'MP') {
            // Fetch only MP products whose product_code starts with 'MP'
            $stockDataQuery->where('product_code', 'like', 'MP%');
        }

        // Fetch stock data
        $stockData = $stockDataQuery->get()
            ->sortBy(function ($item) {
                // Check if stock_product is not null
                if ($item->stock_product) {
                    $typeOrder = ['MACHINE' => 1, 'ACCESSORIES' => 2, 'SPARE' => 3]; // Define type priority
                    $typeRank = $typeOrder[$item->stock_product->type] ?? 4; // Default rank for unknown types
                    return [$typeRank, $item->stock_product->category]; // Sort first by type, then by category
                }
                // Return a default value if stock_product is null
                return [4, 'Unknown']; // You can adjust this based on your needs
            });

        // Organize stock by product and godown
        $stockSummary = [];
        foreach ($stockData as $item) {
            if (!$item->stock_product) continue; // Skip if no product found

            $productCode = $item->product_code ?? 'Unknown'; 
            $productName = $item->stock_product->product_name ?? 'Unknown Product';
            $purchasePrice = $item->stock_product->purchase ?? 0; // Default to 0 if no purchase price
            $godown = $item->godown->name ?? 'N/A';

            if (!isset($stockSummary[$productCode])) {
                $stockSummary[$productCode] = [
                    'product_code' => $productCode,
                    'product_name' => $productName,
                    'purchase_price' => $purchasePrice,
                    'godowns' => [],
                    'total_stock' => 0
                ];
            }

            // Ensure valid array key for godowns
            if (!isset($stockSummary[$productCode]['godowns'][$godown])) {
                $stockSummary[$productCode]['godowns'][$godown] = 0;
            }

            // Update stock based on type (IN or OUT)
            if ($item->type === 'IN') {
                $stockSummary[$productCode]['godowns'][$godown] += $item->quantity;
                $stockSummary[$productCode]['total_stock'] += $item->quantity;
            } elseif ($item->type === 'OUT') {
                $stockSummary[$productCode]['godowns'][$godown] -= $item->quantity;
                $stockSummary[$productCode]['total_stock'] -= $item->quantity;
            }
        }

        // Fetch all Godown Names dynamically
        $allGodowns = GodownModel::where('name', '!=', 'DIRECT DISPATCH')
            ->pluck('name')
            ->toArray();


        // Generate PDF
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L', // Landscape for better table display
            'margin_top' => 10, // Adjusted for header spacing
        ]);

        $mpdf->SetTitle("Stock Report");

        // Define column widths
        $columnWidth = 60 / (count($allGodowns) + 2); // Distributes width evenly

        // Change title based on series value
        $reportTitle = ($series === 'MP') ? 'Stock Report Master Pro' : 'Stock Report';

        // HTML Content (Header Appears Only Once)
        $html = '<div style="text-align:center;">
                    <h2>' . $reportTitle . '</h2>
                    <p>Date: ' . Carbon::now('Asia/Kolkata')->format('d-m-Y h:i A') . '</p>
                </div>';

        // Start table with fixed widths
        $html .= '<table border="1" width="100%" cellpadding="5" cellspacing="0" 
                    style="border-collapse: collapse; font-size: 12px; text-align: center;">
                    <thead>
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 7%;">Part No</th>
                            <th style="width: 28%;">Product Name</th>';

        if ($series === 'MP') {
            // Add heading for 'MP' series products
            $html .= '<div style="text-align:center; font-size: 18px; margin-top: 20px; font-weight: bold;">
                        Stock Report Master Pro
                    </div>';
        }
        
        foreach ($allGodowns as $godown) {
            $html .= "<th style='width: {$columnWidth}%;'>{$godown}</th>";
        }

        if($type == 'with_value')
        {
            $html .= '<th style="width: ' . $columnWidth . '%; font-weight: bold;">Total Stock</th>
                  <th style="width: ' . $columnWidth . '%; font-weight: bold;">Stock Value</th>
                        </tr>
                    </thead>
                    <tbody>';
        }else{
            $html .= '<th style="width: ' . $columnWidth . '%; font-weight: bold;">Total Stock</th>
                  </tr>
              </thead>
              <tbody>';

        }
		
		$totalStockValue = 0; // Initialize total stock value

        // Populate table rows
        $index = 1;
        foreach ($stockSummary as $product) {
            if (!isset($product['product_code']) || !isset($product['product_name'])) {
                continue; // Skip invalid entries
            }

            // Calculate Stock Value
            $stockValue = $product['total_stock'] * $product['purchase_price'];
		    $totalStockValue += $stockValue; // Accumulate total stock value

            $html .= "<tr>
                        <td style='width: 5%;'>{$index}</td>
                        <td style='width: 7%;'>{$product['product_code']}</td>
                        <td style='text-align: left;width: 28%;'>{$product['product_name']}</td>";

            // Fill stock for each godown
            foreach ($allGodowns as $godown) {
                $stockInGodown = $product['godowns'][$godown] ?? 0; // Avoid undefined index
                $html .= "<td style='width: {$columnWidth}%;'>{$stockInGodown}</td>";
            }

            if($type == 'with_value')
            {
                $html .= "<td style='width: {$columnWidth}%; font-weight: bold;'>{$product['total_stock']}</td>
                        <td style='width: {$columnWidth}%; font-weight: bold; text-align: right;'>₹ " . $this->formatInINR($stockValue) . "</td>

                        </tr>";
            }else{
                $html .= "<td style='width: {$columnWidth}%; font-weight: bold;'>{$product['total_stock']}</td>
                        </tr>";
            }
            $index++;
        }
		
		if($type == 'with_value')
        {
            // Add Total Row at the End
            $html .= "<tr style='font-weight: bold; background-color: #f2f2f2;'>
                <td colspan='" . (count($allGodowns) + 4) . "' style='text-align: right; font-weight: bold;'>Total Stock Value:</td>
                <td style='width: {$columnWidth}%; text-align: right;'>₹ " . $this->formatInINR($totalStockValue) . "</td>
            </tr>";
        }

        $html .= '</tbody></table>';

        // Write HTML to PDF
        $mpdf->WriteHTML($html);

        // Define file path
        $filePath = storage_path('app/public/reports/');
        $fileName = 'Stock_Report_' . Carbon::now()->format('YmdHis') . '.pdf';

        // Ensure directory exists
        if (!File::exists($filePath)) {
            File::makeDirectory($filePath, 0755, true);
        }

        // Save PDF file
        $mpdf->Output($filePath . $fileName, 'F');

        // Return download link
        return response()->json([
            'download_url' => asset('storage/reports/' . $fileName),
        ]);
    }

    private function formatInINR($number)
    {
        $explodedNumber = explode('.', $number);
        $integerPart = $explodedNumber[0];
        $decimalPart = isset($explodedNumber[1]) ? '.' . $explodedNumber[1] : '';

        $lastThreeDigits = substr($integerPart, -3);
        $remainingDigits = substr($integerPart, 0, -3);
        
        if ($remainingDigits != '') {
            $remainingDigits = preg_replace("/\B(?=(\d{2})+(?!\d))/", ",", $remainingDigits);
        }
        
        return $remainingDigits . ',' . $lastThreeDigits . $decimalPart;
    }
}
?>
