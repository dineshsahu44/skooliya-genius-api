<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShortenController extends Controller
{
    public function shorten(Request $request)
    {
        $inputString = "/5002/ccsrath";

        // Encode the input string using base64 encoding
        $encodedString = base64_encode($inputString);

        // Truncate the encoded string to a maximum length of 6 characters
        $shortenedEncodedString = Str::limit($encodedString, 6, '');

        return response()->json([
            'shortened_encoded_string' => $shortenedEncodedString
        ]);
    }

    public function decode(Request $request)
    {
        // Retrieve the shortened encoded string from the request
        $shortenedEncodedString = $request->input('shortened_encoded_string');

        // Decode the shortened encoded string
        $decodedString = base64_decode($shortenedEncodedString);

        return response()->json([
            'decoded_string' => $decodedString
        ]);
    }

}
