<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Files and Folders</title>
</head>
<body>
    <h1>Files and Folders</h1>

    <table border="1">
        <thead>
            <tr>
                <th>Serial No.</th>
                <th>Folder Name</th>
                <th>File Name</th>
            </tr>
        </thead>
        <tbody>
            @php
                $serialNo = 1; // Initialize the serial number counter
            @endphp
            @foreach($folderFiles as $folderName => $files)
                @php
                    // Calculate the number of files in the folder
                    $fileCount = count($files);

                    // Calculate the number of empty cells to add
                    $emptyCells = $fileCount % 40 == 0 ? 0 : 40 - ($fileCount % 40);
                @endphp

                @for ($i = 0; $i < $fileCount + $emptyCells; $i++)
                    @php
                        // Get the current file, or set to null if it's an empty cell
                        $file = $i < $fileCount ? $files[$i] : null;
                    @endphp

                    <tr>
                        <td>{{ $serialNo }}</td>
                        <td>{{ strtoupper($folderName) }}</td>
                        <td>{{ $file ? $file->getFilename() : '' }}</td>
                    </tr>
                    @php
                        $serialNo++; // Increment the serial number counter
                    @endphp
                @endfor
            @endforeach
        </tbody>
    </table>
</body>
</html>
