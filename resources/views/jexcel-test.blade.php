<html>

<head>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.8.0/jszip.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.8.0/xlsx.js"></script>

    <script src="https://bossanova.uk/jspreadsheet/v4/jexcel.js"></script>
    <script src="https://jsuites.net/v4/jsuites.js"></script>
    <link rel="stylesheet" href="https://bossanova.uk/jspreadsheet/v4/jexcel.css" type="text/css" />
    <link rel="stylesheet" href="https://jsuites.net/v4/jsuites.css" type="text/css" />
</head>

<body>

    <script>
        var ExcelToJSON = function () {

            this.parseExcel = function (file) {
                var reader = new FileReader();

                reader.onload = function (e) {
                    var data = e.target.result;
                    var workbook = XLSX.read(data, {
                        type: 'binary'
                    });
                    //get the name of First Sheet.
                    var Sheet = workbook.SheetNames[0];
                    
                    //Read all rows from First Sheet into an JSON array.
                    // var excelRows = XLSX.utils;
                    // console.log(excelRows);
                    // return false;
                    // workbook.SheetNames.forEach(function (sheetName) {
                        // Here is your object
                        excelRows = XLSX.utils.sheet_to_row_object_array(workbook.Sheets[firstSheet],{
                            header: 0,
                            // range: { s: { c: 1 }, e: { c: 2 } },
                            defval: "",
                            // origin: 20,
                            // defval: null
                        });
                        var XL_row_object = XLSX.utils.sheet_to_row_object_array(workbook.Sheets[Sheet], {
                            defval: "",
                        });
                        var json_object = JSON.stringify(XL_row_object);
                        console.log(JSON.parse(json_object));
                        //jQuery('#xlx_json').val(json_object);
                       var j = jexcel(document.getElementById('spreadsheet3'), {
                            data: JSON.parse(json_object),
                            allowRenameColumn: false,
                            tableOverflow: true,
                            tableWidth: "1200px",
                            tableHeight: "500px",
                            defaultColWidth: 100,
                            defaultColAlign: 'left',
                            wordWrap: true,

                            columns: [{
                                    title: 'SR',
                                       width: 50
                                },
                                {
                                    title: 'Name',
                                    width: 200
                                },
                                {
                                    title: 'Father',
                                    width: 200
                                },
                                {
                                    title: 'Address',
                                    width: 200
                                },
                                {
                                    title: 'DoB',
                                    width: 100,
                                    type: 'calendar',
                                },
                                {
                                    title: 'Mobile',
                                    width: 100,
                                    maxLenght: 5
                                },
                                {
                                    title: 'Class',
                                    width: 100
                                },
                                {
                                    title: 'Photo',
                                    width: 250
                                },
                                {
                                    title: 'Photo',
                                    width: 250
                                },
                                {
                                    title: 'Photo',
                                    width: 250
                                },
                                {
                                    title: 'Photo',
                                    width: 250
                                },
                            ],
                            filters: true,
                            sorting: true,
                            pagination: 10,
                            
                            search: true,
                            fullscreen: false,
                            allowComments: true,
                        });
                        j.hideIndex();
                    // })
                    
                };

                reader.onerror = function (ex) {
                    console.log(ex);
                };

                reader.readAsBinaryString(file);
            };
        };

        function handleFileSelect(evt) {

            var files = evt.target.files; // FileList object
            var xl2json = new ExcelToJSON();
            xl2json.parseExcel(files[0]);
        }

    </script>

    <form enctype="multipart/form-data">
        <input id="upload" type=file name="files[]">
    </form>
    <div id="spreadsheet3"></div>
    <script>
        document.getElementById('upload').addEventListener('change', handleFileSelect, false);

    </script>
</body>

</html>
