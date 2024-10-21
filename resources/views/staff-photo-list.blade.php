<!DOCTYPE html>
<html>

<head>

    <META Http-Equiv="Cache-Control" Content="no-cache private no-cache must-revalidate" />
    <META Http-Equiv="Cache-Control" Content=" pre-check=0 post-check=0 max-age=0 max-stale = 0" />
    <META Http-Equiv="Pragma" Content="no-cache" />
    <META Http-Equiv="Expires" Content="0" />
    <meta charset="UTF-8">
    <title> Skooliya</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <style>
        .nav-tabs-custom>.nav-tabs {
            display: flex;
            justify-content: space-between;
            border: none;
            /* Remove border */
        }

        .nav-tabs-custom>.nav-tabs>ul {
            margin: 0;
            padding: 0;
            list-style: none;
            display: flex;
            /* Use Flexbox to distribute tabs evenly */
        }

        .nav-tabs-custom>.nav-tabs>li {
            /* background: #222; */
            color: white;
            list-style: none;
            margin: 0;
            /* padding: 20px; */
            border-right: 1px solid rgba(0, 0, 0, 0.1);
            /* font-family: Helvetica, Arial, sans-serif; */
            /* letter-spacing: 3px; */
            cursor: pointer;
            flex-grow: 1;
            /* Allow tabs to grow equally */
            text-align: center;
            /* Center the tab content */
        }

        .nav-tabs-custom>.nav-tabs>li>a {
            color: #b9b9b9;
            background: transparent;
            margin: 0;
            padding: 7px 15px;
            font-size: 17px;
        }

        .nav-tabs-custom>.nav-tabs>li.active {
            /* border-top-color: #3c8dbc; */
            border-top: 0px;
            /* border-bottom-color: #3c8dbc; */
            border-bottom: 3px solid #3c8dbc;
        }

        .nav-tabs-custom>.nav-tabs>li:last-child {
            /* box-shadow: inset 0 -50px 50px -50px rgba(0, 0, 0, 0.2), inset 1px 0 0 rgba(250, 255, 255, .1); */
            border-right: none;
            /* Remove the right border on the last tab */
        }

        /* .nav-tabs-custom > .nav-tabs > li > a:hover {
        color: #000;
    } */

        .special-list {
            margin-bottom: 10px;
            /* padding: 10px 15px; */
            padding: 2px 15px 2px 4px;            
            line-height: 35px;
            border: 2px solid #d5d5d5;
            border-radius: 5px;
            font-size: 18px;
            font-weight: 600;
            box-shadow: rgba(0, 0, 0, 0.2) 10px 10px 20px -12px;
        }

        .success-list {
            background-color: #17ff4947;
            display: flex;
            align-items: center;
        }

        .danger-list {
            background-color: #ff171747;
            display: flex;
            align-items: center;
        }

        .circle-badge {
            position: relative;
            margin-right: 15px;
            margin-left: 3px;
            width: 45px;
            height: 45px;
            line-height: 35px;
            border-radius: 50%;
            color: #fff;
            text-align: center;
            background: #fff;
            border: 1px solid #999;
            font-weight: 600;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        .circle-badge::after {
            content: "+";
            position: absolute;
            top: 90%;
            left: 85%;
            transform: translate(-50%, -50%) rotate(0deg);
            color: #000000;
            font-size: 24px;
            font-weight: 600;
        }

        .flex-row {
            flex-grow: 1;
            line-height: 17px;
        }

        .justify-between {
            display: flex;
            justify-content: space-between;
        }

        .info-panel {
            padding: 5px;
            margin-bottom: 10px;
        }

        .f-s-15 {
            font-size: 15px;
        }

        .f-s-17 {
            font-size: 17px;
        }

        .f-s-25 {
            font-size: 25px;
        }

        .f-w-5 {
            font-weight: 500;
        }

        .f-w-6 {
            font-weight: 600;
        }

        .total-due-panel {
            padding: 5px;
            background-color: #E8ECEB;
            border-radius: 5px;
            text-align: center;
            margin-bottom: 10px;
        }

        .custom-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        .date-input-header{
            border-color: #000000 !important;
            font-size: 1.2em !important;
            font-weight: 600;
        }
        .title-overlap{
            position: absolute; 
            top: -14px; 
            left: 20px; 
            background-color: white; 
            padding-left: 0.5rem; 
            padding-right: 0.5rem; 
            font-weight: 600; 
            z-index: 7;
        }

    </style>
    <link rel="stylesheet" type="text/css" href="https://adminlte.io/themes/AdminLTE/bower_components/bootstrap/dist/css/bootstrap.min.css">
    
    <link rel="stylesheet" href="https://adminlte.io/themes/AdminLTE/bower_components/bootstrap/dist/css/bootstrap.min.css">

    <link rel="stylesheet" href="https://adminlte.io/themes/AdminLTE/bower_components/font-awesome/css/font-awesome.min.css">

    <link rel="stylesheet" href="https://adminlte.io/themes/AdminLTE/bower_components/Ionicons/css/ionicons.min.css">

    <!-- <link rel="stylesheet" href="https://adminlte.io/themes/AdminLTE/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css"> -->
    <!-- below css have multiple widget facilities as datepicker and so-on https://api.jqueryui.com/datepicker/ -->
    <link rel="stylesheet" href="{{ asset('jquery-ui-1.14.0.custom/jquery-ui.css') }}">
    <!-- end below css have multiple widget facilities as datepicker and so-on -->

    <link rel="stylesheet" type="text/css" href="https://web.skooliya.com/css/AdminLTE.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script type="text/javascript" src="https://web.skooliya.com/js/bootstrap.js"></script>
    <script type="text/javascript" src="https://web.skooliya.com/js/AdminLTE/jquery.slimscroll.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.2/jquery-confirm.min.css">
    <script src="https://cdn.jsdelivr.net/npm/gasparesganga-jquery-loading-overlay@2.1.7/dist/loadingoverlay.min.js"></script>

    <!-- <script src="https://adminlte.io/themes/AdminLTE/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js"></script> -->

    <!-- below js have multiple widget facilities as datepicker and so-on https://api.jqueryui.com/datepicker/ -->
    <script src="{{ asset('jquery-ui-1.14.0.custom/jquery-ui.js') }}"></script>
    <!-- end below js have multiple widget facilities as datepicker and so-on -->
    
<script src="https://adminlte.io/themes/AdminLTE/plugins/input-mask/jquery.inputmask.js"></script>
<script src="https://adminlte.io/themes/AdminLTE/plugins/input-mask/jquery.inputmask.date.extensions.js"></script>
<script src="https://adminlte.io/themes/AdminLTE/plugins/input-mask/jquery.inputmask.extensions.js"></script>
<style>
    .crop-button {
        padding: 10px 20px;
        font-size: 16px;
        cursor: pointer;
    }

    .photo-editor {
        width: 100%;
        height: 100%;
        background-color: #2b2b2b;
        position: fixed;
        top: 0;
        left: 0;
        z-index: 9999;
        visibility: hidden;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .photo-editor.visible {
        visibility: visible;
        opacity: 1;
    }

    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 15px;
        background-color: #444;
    }

    .header h1 {
        font-size: 16px;
        margin: 0;
        color: #e5e5e5;
    }

    .header .icons {
        display: flex;
        gap: 10px;
    }

    .header .icon {
        background: none;
        border: none;
        padding: 0;
        cursor: pointer;
    }

    .header .icon img {
        width: 20px;
        height: 20px;
        filter: invert(100%);
    }

    .image-container {
        display: flex;
        justify-content: center;
        align-items: center;
        height: calc(100% - 100px); /* Adjust based on header height */
    }

    .image-container img,
    .image-container video {
        max-width: 100%;
        max-height: 100%;
        display: none;
    }

    .crop-controls {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        padding: 10px;
        background-color: #444;
    }

    .crop-controls button {
        padding: 10px 20px;
        background-color: #555;
        color: #ddd;
        border: none;
        cursor: pointer;
    }

    .crop-controls button:hover {
        background-color: #666;
    }

</style>
<style>
    .floating-button {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background-color: #007bff;
        color: white;
        border: none;
        display: flex;
        justify-content: center;
        align-items: center;
        box-shadow: 0px 2px 10px rgba(0, 0, 0, 0.3);
        z-index: 1000;
        font-size: 24px;
    }
    .floating-button:hover {
        background-color: #0056b3;
    }

    .show{
        display: block !important;
    }
    .hide{
        display: none !important;
    }
</style>
<link rel="stylesheet" href="https://unpkg.com/cropperjs@1.5.12/dist/cropper.min.css">
<script>
    function setSection(classid,sectionid,allclasswithsection,allAllow){
        allclasswithsection = JSON.parse(allclasswithsection);
        classid = "#"+classid;
        sectionid = "#"+sectionid;
        $(sectionid).children('option').remove();
        $(sectionid).append(new Option("Select Section", "")).change();
        // alert($(classid).val());
        if($(classid).val()!='' && $(classid).val()!='All'){
            // console.log(allclasswithsection.find( record => record.class === $(classid).val()))
            $.each(allclasswithsection.find( record => record.class === $(classid).val()).section, function(i, sec) {
                
                if(allAllow==true&&i==0){
                    $(sectionid).append(new Option('All', 'All')).change();
                }
                // $(sectionid).append(new Option(properCase(sec), sec)).change();
                $(sectionid).append(new Option(sec, sec)).change();
            
            });
        }else if($(classid).val()=='All'){
            $(sectionid).append(new Option('All', 'All')).change();
        }
    }
</script>

<style>
    #filterOptions {
        display: none;
        position: absolute;
        right: 0;
        top: 30px; /* Adjusted to fit below the filter icon */
        z-index: 1000;
        background-color: white;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 5px;
        box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
    }
    #filterIcon {
        position: absolute;
        top: 0;
        right: 0;
        z-index: 1001;
    }
    #filterIcon i {
        font-size: 24px;
        cursor: pointer;
    }
</style>
<style>
    .toast {
        visibility: hidden;
        min-width: 250px;
        margin-left: -125px;
        background-color: #333;
        color: #fff;
        text-align: center;
        border-radius: 2px;
        /* padding: 16px; */
        padding: 4px;
        position: fixed;
        z-index: 1000;
        left: 50%;
        bottom: 30px;
        font-size: 17px;
        transition: visibility 0.5s, opacity 0.5s ease-in-out;
        opacity: 0;
    }

    .toast.show {
        visibility: visible;
        opacity: 1;
    }
</style>

</head>

<body class="skin-red sidebar-mini fixed">
    <section class="content">
        <form action="#" method="post" id="student_photo_form">
            <div class="row" style="margin-bottom: 10px;">
                <div class="col-xs-12">
                    <button type="submit" class="col-xs-12 btn btn-info">Get Record</button>
                </div>
            </div>

            <div class="row" style="margin-bottom: 10px;">
                <div class="col-xs-12">
                    <div class="total-due-panel" style="position: relative;">
                        <div class="f-s-17 f-w-5">Total Staff: <span class="f-w-6" style="color: red;" id="studentCount">0</span> / <span class="f-w-6" id="total-student">0</span></div>
                        <div id="filterIcon" style="position: absolute; top: 0; right: 0; z-index: 1001;">
                            <i class="fa fa-filter" style="font-size: 24px; cursor: pointer;"></i>
                        </div>
                        <div id="filterOptions" style="display: none; position: absolute; text-align: left; right: 0; top: 30px; z-index: 1000; background-color: white; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);">
                            <label><input type="radio" name="filter" value="all" id="filterAll" checked> Show All Staff</label><br>    
                            <label><input type="radio" name="filter" value="withPhoto" id="filterWithPhoto"> Show Staff With Photos</label><br>
                            <label><input type="radio" name="filter" value="noPhoto" id="filterNoPhoto"> Show Staff Without Photos</label>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <div class="row" style="max-height: 100vh;overflow: auto;">
            <div class="col-md-12">
                <ul class="custom-list" id="student-list">
                </ul>
            </div>
        </div>
    </section>

    <!-- Floating Button -->
     @if($serverinfo['addpermission']==1)
        <button type="button" class="btn btn-primary floating-button" data-toggle="modal" data-target="#addStaffModal"> + </button>
    @endif

    <!-- Add Student Modal -->
    <div class="modal fade" id="addStaffModal" tabindex="-1" aria-labelledby="addStaffModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                    <h4 class="modal-title">Add Staff</h4>
                </div>
                <form id="addStaffForm" action="#">
                    <div class="modal-body">
                        
                        <div class="form-group">
                            <label for="new-name">Name</label>
                            <input type="text" name="name" class="form-control" id="new-name" required>
                        </div>
                        <div class="form-group">
                            <label for="new-dob">Date of Birth</label>
                            <div class="input-group date">
                                <input type="text" class="form-control date-input-header datepicker" name="dob" placeholder="dd-mm-yyyy" id="new-dob" data-inputmask="'alias': 'dd-mm-yyyy'" value="${dob}" data-mask>
                                <div class="input-group-addon" style="border-color: #000000;">
                                <i class="fa fa-calendar"></i>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="new-contactno">Mobile</label>
                            <input type="text" name="mobile" class="form-control" id="new-contactno">
                        </div>
                        <div class="form-group">
                            <label for="new-address1">Address</label>
                            <input type="text" name="address" class="form-control" id="new-address1">
                        </div>
                        <div class="form-group">
                            <label for="new-section-profile">Role</label>
                            <select name="role_id" class="form-control">
                                @foreach($roles as $id => $role_name)
                                    <option value="{{ $id }}">{{ $role_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <!-- <div class="form-group">
                            <label for="new-status">Status</label>
                            <select class="form-control" id="new-status" required>
                                <option value="Active">Active</option>
                                <option value="Deactive">Deactive</option>
                            </select>
                        </div> -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="saveNewStaff">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="photoEditor" class="photo-editor">
        <div class="header">
            <div class="icons" style="color:white;width: 90%;">
                <button class="icon" id="uploadButton" title="Upload Image"><i class="fa fa-upload"  style="font-size: 20px;"></i> File</button>
                <button class="icon" id="openCameraButton"><i class="fa fa-camera" style="font-size: 20px;"></i> Camera</button>
                <div id="imageSizeDisplay"></div>
            </div>
            <div class="icons" style="color:white;/*width: 10%;*/">
                @php
                    $acceptedExtensions = implode(',',accpectFiles('student-photo-files')['extension-type']);
                @endphp
                <input type="file" id="fileInput" accept="image/png,image/jpeg" data-max-size="{{accpectFiles('student-photo-files')['max-size']*1024}}" style="display:none;">
                <button class="icon close-icon" id="closeEditorButton" title="Close"><i class="fa fa-close" style="font-size: 20px;"></i></button>
            </div>
        </div>
        <div class="image-container">
            <video id="videoElement" style="display:none;" autoplay></video>
            <img id="photo" src="#" alt="Uploaded Image" style="display:none;">
        </div>
        <div class="crop-controls">
            <button class="icon" id="switchCameraButton" style="display:none;"><i class="fa fa-refresh" style="font-size: 20px;"></i></button>
            <button class="crop-button" id="takePhotoButton" style="display:none;">Take Picture</button>
            <button class="crop-button" id="cropButton" style="display:none;">Crop</button>
            <button class="crop-button" id="rotateButton" style="display:none;">Rotate</button>
            <button class="crop-button" id="undoButton" style="display:none;">Undo</button>
            <button class="icon" id="sendButton" style="display:none;">Send</button>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            function updateCount() {
                var visibleCount = $('#student-list li:visible').length;
                $('#studentCount').text(visibleCount);
            }

            function filterStudents() {
                var filterValue = $('input[name="filter"]:checked').val();
                
                if (filterValue === 'noPhoto') {
                    $('#student-list li').each(function() {
                    var photoUrl = $(this).find('.circle-badge').css('background-image');
                    if (photoUrl === 'none' || photoUrl === 'url("none")' || photoUrl === '') {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                    });
                } else if (filterValue === 'withPhoto') {
                    $('#student-list li').each(function() {
                    var photoUrl = $(this).find('.circle-badge').css('background-image');
                    if (photoUrl !== 'none' && photoUrl !== 'url("none")' && photoUrl !== '') {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                    });
                } else if (filterValue === 'all') {
                    $('#student-list li').show();
                }

                updateCount();
            }

            $('#filterIcon').click(function(event) {
                event.stopPropagation();
                $('#filterOptions').toggle();
            });

            $('input[name="filter"]').change(function() {
                filterStudents();
                $('#filterOptions').hide(); // Hide the filter options after selection
            });

            // Hide filter options when clicking outside
            $(document).click(function(event) {
                if (!$(event.target).closest('#filterOptions, #filterIcon').length) {
                    $('#filterOptions').hide();
                }
            });

            // Initialize count and filter on page load
            // filterStudents();
        });
    </script>
    <script>

        function calenderJquery(){
            $('.datepicker').datepicker({
                changeMonth: true,
                changeYear: true,
                autoclose: true,
                dateFormat: "dd-mm-yy",
                yearRange: "1970:"+(new Date().getFullYear()-1),
                autoSize: true,
                beforeShow: function(input, inst) {
                // Dynamically change z-index when showing
                    setTimeout(function() {
                        inst.dpDiv.css({
                            zIndex: 99999999999 // You can adjust this to your needs
                        });
                    }, 0);
                }
            })
            
            $('.datepicker').inputmask('dd-mm-yyyy', { 'placeholder': 'dd-mm-yyyy' });
        }

        function showToast(message, duration = 3000) {
            // Check if a toast div already exists, if not, create one
            let toast = $('#toast');
            if (toast.length === 0) {
                toast = $('<div id="toast" class="toast"></div>');
                $('body').append(toast);
            }

            // Set the message and show the toast
            toast.text(message);
            toast.addClass('show');

            // Hide the toast after the specified duration
            setTimeout(function() {
                toast.removeClass('show');
            }, duration);
        }

        calenderJquery();

        let mainParameterPhoto = "api/updateFacultyPhotoForIDCard?companyid={{ $companyid }}&servername={{ $servername }}&accountid={{ $accountid }}";
        
        let mainParameterProfile = "api/updateFacultyProfile?companyid={{ $companyid }}&servername={{ $servername }}&accountid={{ $accountid }}";
        let mainParameterAddStaff = "api/addFacultyProfile?companyid={{ $companyid }}&servername={{ $servername }}&accountid={{ $accountid }}";
        let mainParameterAddToMachine = "api/addFacultyToMachine?companyid={{ $companyid }}&servername={{ $servername }}&accountid={{ $accountid }}";
        let mainParameterAddToCart = "api/addToCart?companyid={{ $companyid }}&servername={{ $servername }}&accountid={{ $accountid }}";

        const serverinfo = @json($serverinfo);
        const roles = @json($roles);

        $(document).ajaxStart(function(){
            $.LoadingOverlay("show");
        });
        $(document).ajaxStop(function(){
            $.LoadingOverlay("hide");
        });
		// $('.datepicker').datepicker({
		// 	autoclose: true,
		// 	format: "dd-mm-yyyy",
		// 	// immediateUpdates: true,
		// 	todayBtn: true,
		// 	todayHighlight: true
		// }).datepicker("setDate", "0");
        
		// $('.datepicker').inputmask('dd-mm-yyyy', { 'placeholder': 'dd-mm-yyyy' });

        $("#student_photo_form").on("submit", function (e) {
			e.preventDefault(); // Prevent the form from submitting normally
			$.ajax({
				type: 'POST',
				url:   window.location.href,
				data: $(this).serialize(),
				dataType: 'json',
				beforeSend: function (xhr) {
                    xhr.setRequestHeader('Authorization', "{{$auth_token}}");
                },
				complete: function(data) {
					// $.LoadingOverlay("hide");
				},
				success: function(data) {
                    // console.log(data);
                    // var payment=0;
                    var total_student = 0;
                    var list = '';
                    $.each(data['teachers'], function(k, ph) {
                        // Check if photo URL is valid
                        const backgroundStyle = ph['photo'] ? `background-image: url('${ph['photo']}');` : '';
                        // ${serverinfo.updatepermission == 1 ? 'view-profile' : ''}
                        list += addStaffListItem(ph);
                        total_student++;
                    });
                    
                    // console.log(total_student);
                    $('#total-student').text(total_student);
                    $('#studentCount').text(total_student);
                    $('#student-list').html(list);
                    

				},
			});
        });

        function addStaffListItem(ph){
            const backgroundStyle = ph['photo'] ? `background-image: url('${ph['photo']}');` : '';
            // ${serverinfo.updatepermission == 1 ? 'view-profile' : ''}
            var list = `<li class="special-list success-list" data-receipt-details='${JSON.stringify(ph)}'>
                <div class="circle-badge openEditorFromPhotoButton" style="${backgroundStyle}"></div>
                <div class="flex-row view-profile">
                    <div class="justify-between">
                        <div style="width: 40%;">${ph['accountid']}</div>
                        <div style="width: 60%;" class="studentname-text">${properCase(ph['accountname'])}</div>
                    </div>
                    <div class="text-muted justify-between f-s-15">
                        <div style="width: 40%;" class="mobile-text">${ph['mobile']}</div>
                        <div style="width: 60%;" class="role-text">${properCase(roles[ph['role_id']] || '')}</div>
                    </div>
                </div>
                <div>
                    <button class="openEditorFromFileButton" title="Upload Image"><i class="fa fa-upload" style="font-size: 20px;"></i></button>
                </div>
            </li>`;
            return list;
        }

        // view profile start
        $(document).on('click', '.view-profile', function() {
            var currentStudentListItem1 = $(this).closest('li');
            var currentStudentDetails1 = $(this).closest('li').data('receipt-details');
            var backgroundImage = currentStudentListItem1.find('.circle-badge').css('background-image').replace('url("', '').replace('")', '');
            
            // console.log(currentStudentDetails1);

            if (currentStudentDetails1) {
                var roleOptions = Object.entries(roles).map(([key, value]) => `
                    <option value="${key}" ${key == currentStudentDetails1.role_id ? 'selected' : ''}>
                        ${value}
                    </option>
                `).join('');
                // console.log(roleOptions);
                // Convert the date to the format required by the date input (YYYY-MM-DD)
                var dobParts = currentStudentDetails1.dobStaff;
                var dob = currentStudentDetails1.dob;
                // var dobFormatted = `${dobParts[2]}-${dobParts[1]}-${dobParts[0]}`;

                var studentProfileHtml = `
                    <section class="profile" style="padding: 0;margin: 0;">
                        <div class="container">
                            <div class="row">
                                <div class="col-md-12">
                                    <form id="studentForm">
                                        <input type="hidden" value="${currentStudentDetails1.f_id}" id="staff_f_id">
                                        <div style="text-align: center;" >
                                            <img src="${backgroundImage}" width="150px" height="200px">
                                        </div>
                                        <div class="form-group">
                                            <label for="studentid">Staff ID</label>
                                            <input type="text" class="form-control" id="facultyaccountid" value="${currentStudentDetails1.accountid}" readonly>
                                        </div>
                                        <div class="form-group">
                                            <label for="class">Role</label>
                                            <select class="form-control" id="faculty_role_id" name="role_id">
                                                ${roleOptions}
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="name">Name</label>
                                            <input type="text" class="form-control" id="name" value="${currentStudentDetails1.accountname}">
                                        </div>
                                        <div class="form-group">
                                            <label for="dob">Date of Birth</label>
                                            <div class="input-group date">
                                                <input type="text" class="form-control date-input-header datepicker" name="dob" placeholder="dd-mm-yyyy" id="dob" data-inputmask="'alias': 'dd-mm-yyyy'" value="${dob}" data-mask>
                                                <div class="input-group-addon" style="border-color: #000000;">
                                                <i class="fa fa-calendar"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="contactno">Contact Number</label>
                                            <input type="text" class="form-control" id="contactno" value="${currentStudentDetails1.mobile}">
                                        </div>
                                        <div class="form-group">
                                            <label for="address1">Address</label>
                                            <input type="text" class="form-control" id="address1" value="${currentStudentDetails1.address1}">
                                        </div>
                                        <div class="form-group">
                                            <label for="status">Status</label>
                                            <select class="form-control" id="status">
                                                <option value="Active" ${currentStudentDetails1.facultystatus === 'Active' ? 'selected' : ''}>Active</option>
                                                <option value="Deactive" ${currentStudentDetails1.facultystatus === 'Deactive' ? 'selected' : ''}>Deactive</option>
                                            </select>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </section>`;

                var jc = $.confirm({
                    columnClass: 'medium',
                    title: false,
                    content: studentProfileHtml,
                    onContentReady: function () {
                        calenderJquery();
                    },
                    buttons: {
                        tocart: {
                            text: 'Cart',
                            btnClass: 'btn-red',
                            action: function () {
                                var record = {
                                    reid: [
                                        $('#staff_f_id').val()
                                    ],
                                    appid: [
                                        $('#facultyaccountid').val()
                                    ],
                                };
                                // var formData = record;
                                $.ajax({
                                    url: window.location.origin+"/"+mainParameterAddToCart, // Change this URL to your server endpoint
                                    type: 'POST',
                                    data: {
                                        record:record,
                                        OrderFor: 'StaffIDCard'
                                    },
                                    headers: {
                                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                    },
                                    success: function(response) {
                                        // console.log(response);
                                        if (response.success) {
                                            // $.alert(response.msg);
                                            showToast(response.msg, 3000);
                                            jc.close();
                                        } else {
                                            showToast(response.msg, 3000);
                                        }
                                    },
                                    error: function(xhr) {
                                        console.log(xhr);
                                        $.alert('An error occurred while updating the profile');
                                    }
                                });
                            }
                        },
                        machine: {
                            text: 'Machine',
                            btnClass: 'btn-blue',
                            action: function () {
                                var formData = {
                                    staff_f_id: $('#staff_f_id').val(),
                                    facultyaccountid: $('#facultyaccountid').val(),
                                };
                                // console.log(formData);
                                // Add your update logic here
                                // AJAX call to update student profile
                                $.ajax({
                                    url: window.location.origin+"/"+mainParameterAddToMachine, // Change this URL to your server endpoint
                                    type: 'POST',
                                    data: formData,
                                    headers: {
                                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                    },
                                    success: function(response) {
                                        console.log(response);
                                        if (response.success) {
                                            // currentStudentListItem1.find('.studentname-text').text(response.facultyinfo['name']);
                                            // currentStudentListItem1.find('.mobile-text').text(response.facultyinfo['mobile']);
                                            // currentStudentListItem1.find('.role-text').text(properCase(roles[response.facultyinfo['role_id']]));
                                            
                                            // currentStudentListItem1.data('receipt-details',response.facultyinfo);
                                            
                                            // if(response.facultyinfo.facultystatus=="Active"){
                                            //     currentStudentListItem1.css('background-color', '#17ff4947');
                                            // }else{
                                            //     currentStudentListItem1.css('background-color', '#ff6161');
                                            // }

                                            $.alert(response.msg);
                                            jc.close();
                                        } else {
                                            $.alert(response.msg);
                                        }
                                    },
                                    error: function(xhr) {
                                        console.log(xhr);
                                        $.alert('An error occurred while updating the profile');
                                    }
                                });
                            }
                        },
                        submit: {
                            text: 'Update',
                            btnClass: serverinfo.updatepermission == 1 ? 'btn-blue' : 'btn-blue hide',
                            action: function () {
                                var formData = {
                                    staff_f_id: $('#staff_f_id').val(),
                                    facultyaccountid: $('#facultyaccountid').val(),
                                    role_id: $('#faculty_role_id').val(),
                                    name: $('#name').val(),
                                    dob: $('#dob').val(),
                                    contactno: $('#contactno').val(),
                                    address: $('#address1').val(),
                                    status: $('#status').val(),
                                };
                                // console.log(formData);
                                // Add your update logic here
                                // AJAX call to update student profile
                                $.ajax({
                                    url: window.location.origin+"/"+mainParameterProfile, // Change this URL to your server endpoint
                                    type: 'POST',
                                    data: formData,
                                    headers: {
                                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                    },
                                    success: function(response) {
                                        // console.log(response);
                                        if (response.success) {
                                            currentStudentListItem1.find('.studentname-text').text(response.facultyinfo['name']);
                                            currentStudentListItem1.find('.mobile-text').text(response.facultyinfo['mobile']);
                                            currentStudentListItem1.find('.role-text').text(properCase(roles[response.facultyinfo['role_id']]));
                                            
                                            currentStudentListItem1.data('receipt-details',response.facultyinfo);
                                            
                                            if(response.facultyinfo.facultystatus=="Active"){
                                                currentStudentListItem1.css('background-color', '#17ff4947');
                                            }else{
                                                currentStudentListItem1.css('background-color', '#ff6161');
                                            }

                                            $.alert(response.msg);
                                            jc.close();
                                        } else {
                                            $.alert('Failed to update student profile');
                                        }
                                    },
                                    error: function(xhr) {
                                        console.log(xhr);
                                        $.alert('An error occurred while updating the profile');
                                    }
                                });
                            }
                        },
                        close: function () {
                            // Handle the close action
                        }
                    }
                });
                
            }
        });
        // view profile end

        
        $("#addStaffForm").on("submit", function (e) {
			e.preventDefault(); // Prevent the form from submitting normally
			$.ajax({
				type: 'POST',
				url: window.location.origin+"/"+mainParameterAddStaff,
				data: $(this).serialize(),
				dataType: 'json',
				beforeSend: function (xhr) {
                    xhr.setRequestHeader('Authorization', "{{$auth_token}}");
                },
				complete: function(data) {
					// $.LoadingOverlay("hide");
				},
				success: function(response) {
                    if (response.success==1) {
                        $('#addStaffForm')[0].reset();
                        
                        list = addStaffListItem(response.facultyinfo);
                        $('#student-list').append(list);
                        $.alert(response.msg);
                    } else {
                        $.alert(response.msg);
                    }
                }
            });
        });
        function getIndianCurrency(number) {
            var decimal = Math.round((number - (no = Math.floor(number))) * 100);
            var hundred = '';
            var digits_length = no.toString().length;
            var i = 0;
            var str = [];
            var words = {
                0: '', 1: 'one', 2: 'two', 3: 'three', 4: 'four', 5: 'five', 6: 'six',
                7: 'seven', 8: 'eight', 9: 'nine', 10: 'ten', 11: 'eleven', 12: 'twelve',
                13: 'thirteen', 14: 'fourteen', 15: 'fifteen', 16: 'sixteen', 17: 'seventeen',
                18: 'eighteen', 19: 'nineteen', 20: 'twenty', 30: 'thirty', 40: 'forty',
                50: 'fifty', 60: 'sixty', 70: 'seventy', 80: 'eighty', 90: 'ninety'
            };

            var digits = ['', 'hundred', 'thousand', 'lakh', 'crore'];

            while (i < digits_length) {
                var divider = (i === 2) ? 10 : 100;
                number = Math.floor(no % divider);
                no = Math.floor(no / divider);
                i += (divider === 10) ? 1 : 2;

                if (number) {
                    var plural = (str.length && number > 9) ? 's' : '';
                    hundred = (str.length === 1 && str[0]) ? ' and ' : '';
                    str.push((number < 21) ? words[number] + ' ' + digits[str.length] + plural + ' ' + hundred : words[Math.floor(number / 10) * 10] + ' ' + words[number % 10] + ' ' + digits[str.length] + plural + ' ' + hundred);
                } else {
                    str.push('');
                }
            }

            var Rupees = str.reverse().join('');
            // var paise = (decimal > 0) ? "." + (words[Math.floor(decimal / 10)] + " " + words[decimal % 10]) + ' Paise' : '';

            var paise = (decimal > 0) ? "" + (words[Math.floor(decimal / 10)] + " " + words[decimal % 10]) + ' Paise' : '';

            return (Rupees ? Rupees + ' Rupees ' : '') + paise;
        }
    </script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.31.3/js/jquery.tablesorter.min.js">
    </script>
    <script type="text/JavaScript" src="https://cdnjs.cloudflare.com/ajax/libs/jQuery.print/1.6.0/jQuery.print.js">
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.2/jquery-confirm.min.js"></script>
    <!-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> -->
    <script src="https://unpkg.com/cropperjs@1.5.12/dist/cropper.min.js"></script>

    <script>
        $(document).ready(function () {
            if ($('input[type="text"]').first()) {

            } else {
                $('#class').first().focus();
                $('#txtSec').first().focus();
            }

            $('input, textarea,redio,checkbox').each(
                function (index) {
                    //   var input = $(this);
                    if ($(this).attr('required') && $(this).val() == '') {
                        $(this).css('border-color', '#F44336');
                    }
                    //alert('Type: ' + $(this).attr('type') + 'Name: ' + $(this).attr('name') + 'Value: ' + $(this).val());
                }
            );
            $('input, textarea,redio,checkbox').blur(
                function (index) {
                    if ($(this).val() == '' && $(this).attr('required')) {
                        $(this).css('border-color', '#F44336');
                    } else {
                        $(this).css('border-color', '#d2d6de');
                    }
                }
            );
            $('select').each(
                function (index) {
                    //   var input = $(this);
                    if ($(this).attr('required')) {
                        $(this).css('border-color', '#F44336');
                    }
                    //alert('Type: ' + $(this).attr('type') + 'Name: ' + $(this).attr('name') + 'Value: ' + $(this).val());
                }
            );
            $('select').change(
                function (index) {
                    //   var input = $(this);
                    if ($(this).val() == '' && $(this).attr('required')) {
                        $(this).css('border-color', '#F44336');
                    } else {
                        $(this).css('border-color', '#d2d6de');
                    }
                    //alert('Type: ' + $(this).attr('type') + 'Name: ' + $(this).attr('name') + 'Value: ' + $(this).val());
                }
            );
            setTimeout(function () {
                $('iframe').css('display', 'none');
            }, 1000);

        });
        $(document).on('each blur change keypress keyup', '.required', function (event) {
            if ($(this).val() == '') {
                $(this).css('border-color', '#F44336');
            } else {
                $(this).css('border-color', '#d2d6de');
            }
        })
        $(document).on('input', '.cap', function (event) {
            this.value = this.value.toUpperCase();
        })
        $(document).on('input', '.num', function (event) {
            this.value = this.value.replace(/[^0-9]+/, '');
        })
        $(document).on('input', '.num-alfa', function (event) {
            this.value = this.value.replace(/[^A-Za-z0-9 _]+/, '');
        })
        $(document).on('input', '.alfa', function (event) {
            this.value = this.value.replace(/[^A-Za-z _]+/, '');
        })
        $(document).on('input', '.only-num-alfa', function (event) {
            this.value = this.value.replace(/[^A-Za-z0-9]+/, '');
        })
        $(document).on('input', '.float', function (event) {
            this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');
        });

        function properCase(string) {
            str = string.toLowerCase().replace(/\b[a-z]/g, function (letter) {
                return letter.toUpperCase();
            });
            return str;
        }

        function danger(text = '') {
            //alert();
            setTimeout(function () {
                $('#js-flash-message-box').fadeOut('slow');
            }, 5000);
            setTimeout(function () {
                $('#js-flash-message-box').remove();
            }, 6000);
            return '<div id="js-flash-message-box" class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><h4><i class="icon fa fa-ban"></i>' +
                text + '</h4></div>';
        }

        function success(text = '') {
            //alert();
            setTimeout(function () {
                $('#js-flash-message-box').fadeOut('slow');
            }, 5000);
            setTimeout(function () {
                $('#js-flash-message-box').remove();
            }, 6000);
            return '<div id="js-flash-message-box" class="alert alert-success alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><h4><i class="icon fa fa-check"></i>' +
                text + '</h4></div>';
        }

        function getDateInPart(date = null) {
            // date format -> "12-10-2020" //date.split("-").reverse().join("-")
            var currentTime = date == null ? new Date() : new Date(date);
            // returns the month (from 0 to 11)
            var month = currentTime.getMonth() + 1;
            // returns the day of the month (from 1 to 31)
            var day = currentTime.getDate()
            // returns the year (four digits)
            var year = currentTime.getFullYear();
            var yy = year.toString().substr(-2);
            return {
                "dd": day,
                "mm": month,
                "yyyy": year,
                "yy": yy
            };
        }
        // const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September",
        //     "October", "November", "December"
        // ];

    </script>


    <script>
        let cropper; // Define cropper in the global scope
        let originalImageData;
        let videoStream;
        let usingFrontCamera = false; // Default to using the back camera
        let currentStudentDetails = null;
        let currentStudentListItem = null;
        $(document).ready(function () {

            // Trigger file upload
            $(document).on('click', '.openEditorFromFileButton', function (event) {
                currentStudentListItem = $(this).closest('li');
                currentStudentDetails = $(this).closest('li').data('receipt-details');
                // $(this).closest('li').
                // console.log('Student details:', currentStudentDetails);
                $('#fileInput').click();
            });
            $(document).on('click', '.openEditorFromPhotoButton', function (event) {
                currentStudentListItem = $(this).closest('li');
                currentStudentDetails = $(this).closest('li').data('receipt-details');
                // console.log('Student details:', currentStudentDetails);
                $('#openCameraButton').click();
            });

            $('#closeEditorButton').click(function () {
                closeEditor();
            });

            $('#uploadButton').click(function () {
                $('#fileInput').click();
            });

            $('#fileInput').change(function (event) {
                const file = event.target.files[0];
                if (file) {
                    // console.log('File selected:', file);
                    closeVideoStream();
                    $('#photoEditor').addClass('visible');
                    if (file.size > 100 * 1024) { // Check if file size is greater than 100 KB
                        compressImage(file, function (compressedFile) {
                            // console.log('Compressed file:', compressedFile);
                            processImage(compressedFile);
                            displayImageSize(compressedFile);
                        });
                    } else {
                        processImage(file);
                        displayImageSize(file);
                    }
                    $('#cropButton').show();
                    $('#rotateButton').show();
                    $('#undoButton').hide();
                    $('#takePhotoButton').hide();
                    $('#sendButton').hide();
                }
            });

            $('#cropButton').click(function () {
                if (!cropper) return;

                const croppedCanvas = cropper.getCroppedCanvas();
                if (croppedCanvas) {
                    const croppedDataURL = croppedCanvas.toDataURL('image/png');
                    compressImageDataUrl(croppedDataURL, function (compressedDataURL) {
                        // $('#photo').attr('src', croppedDataURL).show();
                        $('#photo').attr('src', compressedDataURL).show();
                        fitImageToCropArea($('#photo')[0]);
                        $('#undoButton').show();
                        $('#cropButton').hide();
                        $('#rotateButton').hide();
                        $('#switchCameraButton').hide();
                        $('#sendButton').show();
                        // displayImageSizeFromSrc(croppedDataURL);
                        displayImageSizeFromSrc(compressedDataURL);
                    });
                } else {
                    console.error('Failed to get cropped canvas');
                }
            });

            $('#rotateButton').click(function () {
                if (cropper) {
                    cropper.rotate(90);
                }
            });

            $('#undoButton').click(function () {
                if (originalImageData) {
                    $('#photo').attr('src', originalImageData).show();
                    fitImageToPage($('#photo')[0]);
                    initCropper($('#photo')[0]);
                    $('#undoButton').hide();
                    $('#cropButton').show();
                    $('#rotateButton').show();
                    $('#sendButton').hide();
                    displayImageSizeFromSrc(originalImageData);
                }
            });

            $('#openCameraButton').click(async function () {
                await openCamera();
            });

            $('#switchCameraButton').click(async function () {
                usingFrontCamera = !usingFrontCamera;
                await openCamera();
            });

            async function openCamera() {
                closeVideoStream();
                try {
                    const constraints = {
                        video: {
                            facingMode: usingFrontCamera ? 'user' : 'environment'
                        }
                    };
                    videoStream = await navigator.mediaDevices.getUserMedia(constraints);
                    const videoElement = $('#videoElement')[0];
                    videoElement.srcObject = videoStream;
                    $('#photoEditor').addClass('visible');
                    $('#videoElement').show();
                    $('#photo').hide();
                    $('#undoButton').hide();
                    $('#cropButton').hide();
                    $('#rotateButton').hide();
                    $('#takePhotoButton').show();
                    $('#sendButton').hide();
                    $('#switchCameraButton').show();
                } catch (error) {
                    console.error('Error accessing camera: ', error);
                }
            }

            $('#takePhotoButton').click(function () {
                const videoElement = $('#videoElement')[0];
                const canvas = document.createElement('canvas');
                canvas.width = videoElement.videoWidth;
                canvas.height = videoElement.videoHeight;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(videoElement, 0, 0, canvas.width, canvas.height);

                const imageDataURL = canvas.toDataURL('image/png');
                processImageDataURL(imageDataURL);
                closeVideoStream();
            });

            $('#sendButton').click(function () {
                sendImage();
            });

        });

        function closeVideoStream() {
            if (videoStream) {
                let tracks = videoStream.getTracks();
                tracks.forEach(track => track.stop());
                videoStream = null;
            }
            $('#videoElement').hide();
            $('#takePhotoButton').hide();
            $('#switchCameraButton').hide();
        }

        function closeEditor() {
            $('#photoEditor').removeClass('visible');
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            closeVideoStream();
            $('#photo').attr('src', '').hide();
            $('#cropButton').hide();
            $('#rotateButton').hide();
            $('#undoButton').hide();
            $('#sendButton').hide();
            $('#imageSizeDisplay').text('');
        }

        function processImage(file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                const photo = $('#photo');
                const imageDataURL = e.target.result;
                console.log('Image data URL:', imageDataURL);
                photo.attr('src', imageDataURL).show();

                originalImageData = imageDataURL;

                fitImageToPage(photo[0]);
                $('#cropButton').show();
                $('#rotateButton').show();
                initCropper(photo[0]);
            };
            reader.readAsDataURL(file);
        }

        function processImageDataURL(dataURL) {
            const photo = $('#photo');
            // console.log('Image data URL:', dataURL);
            photo.attr('src', dataURL).show();

            originalImageData = dataURL;

            fitImageToPage(photo[0]);
            $('#cropButton').show();
            $('#rotateButton').show();
            initCropper(photo[0]);
        }

        function initCropper(imageElement) {
            if (cropper) {
                cropper.destroy();
            }

            cropper = new Cropper(imageElement, {
                viewMode: 1,
                dragMode: 'crop',
                autoCropArea: 0.5,
                aspectRatio: NaN,
                restore: false,
                guides: false,
                center: false,
                highlight: true,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleDragModeOnDblclick: false,
                ready: function () {
                    const imageData = cropper.getImageData();
                    const imageWidth = imageData.width;
                    const imageHeight = imageData.height;
                    const cropBoxLeft = (imageWidth - 200) / 2;

                    cropper.setCropBoxData({
                        top: 0,
                        width: 150,
                        height: 180
                    });
                }
            });
        }

        function fitImageToPage(imageElement) {
            const maxWidth = $(window).width() * 0.9;
            const maxHeight = $(window).height() * 0.9;

            const image = new Image();
            image.onload = function () {
                const aspectRatio = image.width / image.height;

                let newWidth = image.width;
                let newHeight = image.height;

                if (newWidth > maxWidth) {
                    newWidth = maxWidth;
                    newHeight = newWidth / aspectRatio;
                }

                if (newHeight > maxHeight) {
                    newHeight = maxHeight;
                    newWidth = newHeight * aspectRatio;
                }

                $(imageElement).css({
                    width: newWidth + 'px',
                    height: newHeight + 'px',
                });
            };
            image.src = $(imageElement).attr('src');
        }

        function fitImageToCropArea(imageElement) {
            if (!cropper) return;
            cropper.destroy();
            const cropBoxData = cropper.getCropBoxData();
            const canvasData = cropper.getCanvasData();

            const scaleX = imageElement.naturalWidth / canvasData.naturalWidth;
            const scaleY = imageElement.naturalHeight / canvasData.naturalHeight;

            const x = cropBoxData.left - canvasData.left;
            const y = cropBoxData.top - canvasData.top;
            const width = cropBoxData.width;
            const height = cropBoxData.height;

            $(imageElement).css({
                width: width * scaleX + 'px',
                height: height * scaleY + 'px',
                marginLeft: -x * scaleX + 'px',
                marginTop: -y * scaleY + 'px',
            });
        }

        function compressImage(file, callback) {
            console.log("compressImage");
            const reader = new FileReader();
            reader.onload = function (event) {
                const img = new Image();
                img.src = event.target.result;

                img.onload = function () {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');

                    const maxWidth = 800; // Max width for the image
                    const maxHeight = 600; // Max height for the image
                    let width = img.width;
                    let height = img.height;

                    // Calculate new size maintaining aspect ratio
                    if (width > height) {
                        if (width > maxWidth) {
                            height *= maxWidth / width;
                            width = maxWidth;
                        }
                    } else {
                        if (height > maxHeight) {
                            width *= maxHeight / height;
                            height = maxHeight;
                        }
                    }

                    canvas.width = width;
                    canvas.height = height;

                    // Draw image on canvas
                    ctx.drawImage(img, 0, 0, width, height);

                    // Convert canvas to Blob with desired quality and size
                    canvas.toBlob(function (blob) {
                        const compressedFile = new File([blob], file.name, { type: file.type, lastModified: Date.now() });

                        // Check if compressed file size is within 100 KB
                        if (compressedFile.size <= 100 * 1024) {
                            callback(compressedFile);
                        } else {
                            // If compressed size exceeds 100 KB, reduce quality and try again
                            canvas.toBlob(function (secondBlob) {
                                const secondCompressedFile = new File([secondBlob], file.name, { type: file.type, lastModified: Date.now() });
                                callback(secondCompressedFile);
                            }, file.type, 0.9); // Adjust quality (0.5 means 50% quality) as needed
                        }
                    }, file.type, 1.0); // Adjust quality (0.7 means 70% quality) as needed
                };
            };
            reader.readAsDataURL(file);
        }

        function compressImageDataUrl(dataUrl, callback) {
            // console.log("compressImageDataUrl");
            const img = new Image();
            img.src = dataUrl;
            $compresssionFirst = 1.0;
            $compresssionSecond = 0.9;

            img.onload = function () {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');

                const maxWidth = 800; // Max width for the image
                const maxHeight = 600; // Max height for the image
                let width = img.width;
                let height = img.height;

                // Calculate new size maintaining aspect ratio
                if (width > height) {
                    if (width > maxWidth) {
                        height *= maxWidth / width;
                        width = maxWidth;
                    }
                } else {
                    if (height > maxHeight) {
                        width *= maxHeight / height;
                        height = maxHeight;
                    }
                }

                canvas.width = width;
                canvas.height = height;

                // Draw image on canvas
                ctx.drawImage(img, 0, 0, width, height);

                // Convert canvas to data URL with desired quality
                canvas.toDataURL('image/jpeg', 1.0); // Adjust quality (0.7 means 70% quality) as needed

                // Check if compressed data URL size is within 100 KB
                if (dataUrl.length <= 100 * 1024) {
                    // console.log("compressImageDataUrl in if condition: "+dataUrl.length);
                    callback(dataUrl);
                } else {
                    // If compressed size exceeds 100 KB, reduce quality and try again
                    // console.log("compressImageDataUrl in else condition: "+dataUrl.length/1000+"KB");
                    canvas.toBlob(function (blob) {
                        const reader = new FileReader();
                        reader.onloadend = function () {
                            callback(reader.result);
                        };
                        reader.readAsDataURL(blob);
                    }, 'image/jpeg', 0.9); // Adjust quality (0.5 means 50% quality) as needed
                }
            };
        }

        function displayImageSize(file) {
            const fileSizeKB = Math.round(file.size / 1024);
            $('#imageSizeDisplay').text(`Image Size: ${fileSizeKB} KB`);
        }

        function displayImageSizeFromSrc(imageSrc) {
            // Calculate the size of the base64 encoded image
            const base64Length = imageSrc.length - (imageSrc.indexOf(',') + 1);
            const padding = (imageSrc.charAt(imageSrc.length - 2) === '=') ? 2 : ((imageSrc.charAt(imageSrc.length - 1) === '=') ? 1 : 0);
            const fileSizeBytes = (base64Length * 0.75) - padding;

            const fileSizeKB = Math.round(fileSizeBytes / 1024);
            $('#imageSizeDisplay').text(`Image Size: ${fileSizeKB} KB`);
        }

        function sendImage() {
            const imageDataURL = $('#photo').attr('src');
            if (!imageDataURL) {
                alert('No image to send.');
                return;
            }

            // Convert image data URL to JPEG Blob and handle the promise
            dataURLtoJpegBlob(imageDataURL).then((jpegBlob) => {
                const formData = new FormData();
                formData.append('attachment', jpegBlob, '_'+currentStudentDetails.accountid+'_staff_photo.jpg');
                formData.append('activitytype', 'updatephoto');
                formData.append('facultyaccountid', currentStudentDetails.accountid);

                $.ajax({
                    url: window.location.origin + "/" + mainParameterPhoto, // Change this URL to your server endpoint
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('Authorization', "Token");
                    },
                    success: function (response) {
                        if (response.success == 1) {
                            currentStudentListItem.find('.circle-badge').css('background-image', `url(${response.imageurl})`);
                            closeEditor();
                        }
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        alert('Failed to upload image: ' + errorThrown);
                    }
                });
            }).catch((error) => {
                console.error('Error converting image to JPEG blob:', error);
                alert('Failed to process image.');
            });
        }

        function dataURLtoJpegBlob(dataURL) {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const img = new Image();
            img.src = dataURL;

            return new Promise((resolve, reject) => {
                img.onload = function() {
                    canvas.width = img.width;
                    canvas.height = img.height;
                    ctx.drawImage(img, 0, 0);

                    canvas.toBlob((blob) => {
                        if (blob) {
                            resolve(blob);
                        } else {
                            reject(new Error('Canvas to Blob conversion failed'));
                        }
                    }, 'image/jpeg');
                };

                img.onerror = function() {
                    reject(new Error('Image loading failed'));
                };
            });
        }


    </script>

</body>

</html>
