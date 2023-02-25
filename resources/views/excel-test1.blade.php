<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <style>
        .red{
            background-color:#ff5d5d;
        }
    </style>
<script src="https://code.jquery.com/jquery-2.2.4.js"></script>
<script src="https://bossanova.uk/jspreadsheet/v4/jexcel.js"></script>
<script src="https://jsuites.net/v4/jsuites.js"></script>
<link rel="stylesheet" href="https://bossanova.uk/jspreadsheet/v4/jexcel.css" type="text/css" />
<link rel="stylesheet" href="https://jsuites.net/v4/jsuites.css" type="text/css" />

    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.13.5/xlsx.full.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.13.5/jszip.js"></script>
</head>
<body>
    <input type="file" id="fileUpload" />
    <input type="button" id="upload" value="Upload" onclick="Upload()" />
    <hr />
    <div id="spreadsheet3"></div>



    <script type="text/javascript">
        var header = ["Sr","Name","FatherName","Address","DateofBirth","Mobile","Class","Photo",
                    "Temp1","Temp2","temp3"];
          
        function Upload() {
            //Reference the FileUpload element.
            var fileUpload = document.getElementById("fileUpload");

            //Validate whether File is valid Excel file.
            var regex = /^([a-zA-Z0-9\s_\\.\-:])+(.xls|.xlsx)$/;
            if (regex.test(fileUpload.value.toLowerCase())) {
                if (typeof (FileReader) != "undefined") {
                    var reader = new FileReader();

                    //For Browsers other than IE.
                    if (reader.readAsBinaryString) {
                        reader.onload = function (e) {
                            ProcessExcel(e.target.result);
                        };
                        reader.readAsBinaryString(fileUpload.files[0]);
                    } else {
                        //For IE Browser.
                        reader.onload = function (e) {
                            var data = "";
                            var bytes = new Uint8Array(e.target.result);
                            for (var i = 0; i < bytes.byteLength; i++) {
                                data += String.fromCharCode(bytes[i]);
                            }
                            console.log(data);
                            ProcessExcel(data);
                        };
                        reader.readAsArrayBuffer(fileUpload.files[0]);
                    }
                } else {
                    alert("This browser does not support HTML5.");
                }
            } else {
                alert("Please upload a valid Excel file.");
            }
        };
        var changed = function(instance, cell, x, y, value) {
            var cellName = jexcel.getColumnNameFromId([x,y]);
            $(cell).addClass('edition');
            console.log(instance,cellName, x, y, value);
        };
        function ProcessExcel(data) {
            // console.log(data);
            //Read the Excel File data.
            var workbook = XLSX.read(data, {
                type: 'binary'
            });

            //Fetch the name of First Sheet.
            var firstSheet = workbook.SheetNames[0];

            //Read all rows from First Sheet into an JSON array.
            excelRows = XLSX.utils.sheet_to_row_object_array(workbook.Sheets[firstSheet],{
                header: 0,
                // range: { s: { c: 1 }, e: { c: 2 } },
                defval: "",
                // origin: 20,
                // defval: null
            });
            
            const worksheet = workbook.Sheets[workbook.SheetNames[0]];
            var excelRowsAll = [];
            $.each(excelRows, function (i, r) {
                var row ={};
                var r = r;
                $.each(header,function(key,value){
                    if(key===4){
                        row[value] = parseDate(r[Object.keys(r)[key]]);
                    }else{
                        row[value] = r[Object.keys(r)[key]];
                    }
                    
                })
                excelRowsAll.push(row);
            });
            // console.log(excelRowsAll);
            // return false;
            console.log(excelRowsAll,worksheet["!ref"],_buildColumnsArray(worksheet["!ref"]));
            // var json_object = JSON.stringify(excelRowsAll);
            // JSON.parse(json_object)
            var j = jexcel(document.getElementById('spreadsheet3'), {
                data: excelRowsAll,
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
                        options: { format:'DD-MM-YYYY' },
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
                ],
                filters: true,
                sorting: true,
                onchange: changed,
                // pagination: 10,
                
                search: true,
                fullscreen: false,
                allowComments: true,
            });
            // j.hideIndex();
        };
        function _buildColumnsArray(range) {
            var i,
                res = [],
                rangeNum = range.split(':').map(function(val) {
                return alphaToNum(val.replace(/[0-9]/g, ''));
                }),
                start = rangeNum[0],
                end = rangeNum[1] + 1;

            for (i = start; i < end ; i++) {
            res.push(numToAlpha(i));
            }
            return res;
        }
        function numToAlpha(num) {

            var alpha = '';

            for (; num >= 0; num = parseInt(num / 26, 10) - 1) {
            alpha = String.fromCharCode(num % 26 + 0x41) + alpha;
            }

            return alpha;
        }
        function alphaToNum(alpha) {

            var i = 0,
                num = 0,
                len = alpha.length;

            for (; i < len; i++) {
            num = num * 26 + alpha.charCodeAt(i) - 0x40;
            }

            return num - 1;
        }

        function parseDate(str) {
            var m = str.match(/^(\d{1,2})-(\d{1,2})-(\d{4})$/);
            if(m){
                date = m[3]+'-'+(m[2])+'-'+m[1];
                return m?isDate(date)?date:'':'';
            }else
                return '';
        }

        function isDate(txtDate)
        {
            var currVal = txtDate;
            if(currVal == '')
                return false;
            
            var rxDatePattern = /^(\d{4})(\/|-)(\d{1,2})(\/|-)(\d{1,2})$/; //Declare Regex
            var dtArray = currVal.match(rxDatePattern); // is format OK?
            
            if (dtArray == null) 
                return false;
            
            //Checks for mm/dd/yyyy format.
            dtMonth = dtArray[3];
            dtDay= dtArray[5];
            dtYear = dtArray[1];        
            
            if (dtMonth < 1 || dtMonth > 12) 
                return false;
            else if (dtDay < 1 || dtDay> 31) 
                return false;
            else if ((dtMonth==4 || dtMonth==6 || dtMonth==9 || dtMonth==11) && dtDay ==31) 
                return false;
            else if (dtMonth == 2) 
            {
                var isleap = (dtYear % 4 == 0 && (dtYear % 100 != 0 || dtYear % 400 == 0));
                if (dtDay> 29 || (dtDay ==29 && !isleap)) 
                        return false;
            }
            return true;
        }
    </script>
</body>
</html>
