<!DOCTYPE html>
<html>
<head>
    <title>Super Steel - Ace Care</title>
    <style>
        /* Container for the header (image on the left, text on the right) */

        .header {
            width: 100%;
            padding-top: 15px;
        }
        .header img {
            width: 100%!important;
            display: block;
            height: auto;
        }

        /* Center align for the user name */
        .username {
            text-align: center;
            flex: 1;
            margin-left: 30px;
        }

        /* Right align for the text */
        .product-details {
            flex-grow: 2;
            text-align: right;
            margin-left: 30px; /* Adjust margin for spacing */
        }

        /* Styling for the table */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid black;
            text-align: center; /* Center-align all table content */
            padding: 10px;
        }

        /* Styling for table headers */
        th {
            background-color: grey;
            color: white;
            padding: 10px;
            text-align: left;
        }

        /* Specific styling for the "PRICE" column */
        .price-column {
            background-color: lightblue;
        }

        /* Image styling */
        img {
            /* width: 80px; */
            height: auto;
        }

        /* Ensuring the ITEM and MODEL columns are centered and justified */
        .center-text {
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Title Box -->

    <div class="header">
        <img src="{{ asset('storage/uploads/' . $bannerName . '.jpg') }}" alt="Logo" width="100%">
    </div>

    <!-- Table for the Items -->
    <table>
        <thead>
            <tr>
                <th class="center-text">S.NO</th>
                <th class="center-text">IMAGE</th>
                <th class="center-text">ITEM NO</th>
                <th class="center-text">ITEM</th>
                <th class="center-text">PRICE</th>
            </tr>
        </thead>
        <tbody>
            @foreach($get_product_details as $index => $item)
				<tr>
					<td class="center-text">{{ $index + 1 }}</td>
					<td class="center-text"><img width="80px" src="{{ public_path($item->product_image)}}" alt="{{ $item->print_name }}"></td>
					<td class="center-text">{{ $item->product_code }}</td>
					<td class="center-text">{{ $item->product_name }}</td>
					<td class="center-text">{{ $item->price }}</td>
				</tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
