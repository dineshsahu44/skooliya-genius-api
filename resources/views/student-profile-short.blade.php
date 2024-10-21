<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Card</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            font-family: 'Open Sans', sans-serif;
            font-size: 16px;
            user-select: none;
        }

        $base-text-color: #151515;
        $base-link-color: #1daaff;
        $base-hover-color: darken($base-link-color, 20);

        $profile-card-size: 500px;
        $profile-avatar-size: 150px;

        .profile {
            max-width: 500px;
            border: 1px solid #d4d4d4;
            border-radius: 20px;
            padding: 2em;
            margin: 1em auto;
            display: flex;
            flex-flow: row wrap;
            justify-content: space-between;
            align-items: center;
            align-content: center;
            position: relative;
        }

        .profile figure {
            margin: 0;
        }

        .profile figure img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            padding: 10px;
            box-shadow: 0px 0px 20px rgba(21, 21, 21, 0.15);
        }

        .profile header h1 {
            margin: 0;
            padding: 0;
            line-height: 1;
        }

        .profile header h1 small {
            display: block;
            clear: both;
            font-size: 18px;
            opacity: 0.6;
        }

        .profile main {
            display: none;
        }

        .profile main dl {
            display: block;
            width: 100%;
        }

        .profile main dl dt,
        .profile main dl dd {
            float: left;
            padding: 8px 5px;
            margin: 0;
            border-bottom: 1px solid #d4d4d4;
        }

        .profile main dl dt {
            width: 30%;
            padding-right: 10px;
            font-weight: bold;
        }

        .profile main dl dt::after {
            content: ":";
        }

        .profile main dl dd {
            width: 70%;
        }

        .profile main dl a {
            padding-right: 5px;
        }

        .profile .toggle {
            position: absolute;
            background: #fff;
            top: 30px;
            left: 30px;
            width: 40px;
            height: 40px;
            line-height: 40px;
            font-size: 24px;
            text-align: center;
            z-index: 20;
            vertical-align: middle;
            box-shadow: 0px 0px 10px rgba(21, 21, 21, 0.15);
            cursor: pointer;
            border-radius: 20px;
            user-select: none;
            transition: box-shadow 300ms ease;
        }

        .profile .toggle:hover {
            box-shadow: 0px 0px 10px rgba(21, 21, 21, 0.25);
        }

        .view_details {
            position: absolute;
            top: -5000px;
            left: -5000px;
        }

        label {
            display: block;
            cursor: pointer;
        }

        <blade media|%20screen%20and%20(max-width%3A%20520px)%20%7B%0D>.profile {
            padding: 1em;
            margin: 1em;
        }

        .profile img {
            max-width: 100%;
            height: auto;
        }

        .profile main dl,
        .profile main dl dt,
        .profile main dl dd {
            display: block;
            width: 100%;
            float: none;
        }

        .profile main dl dt {
            border-bottom: none;
        }

        .profile main dl dd {
            margin-bottom: 10px;
        }

        .profile .toggle {
            top: 15px;
            left: 15px;
        }
        /* Basic button styling */
        .button-sm {
            display: inline-block; /* Make the element inline-block */
            background-color: #000; /* Blue background */
            color: white; /* White text color */
            padding: 10px 20px; /* Smaller padding around the text */
            text-align: center; /* Center text */
            text-decoration: none; /* Remove underline */
            font-size: 14px; /* Smaller font size */
            margin: 4px 2px; /* Margin around the button */
            cursor: pointer; /* Pointer cursor on hover */
            border-radius: 8px; /* Rounded corners */
            transition: background-color 0.3s ease; /* Smooth transition for background color */
        }

        /* Hover state */
        .button-sm:hover {
            background-color: #0056b3; /* Darker blue on hover */
        }

        /* Active state */
        .button-sm:active {
            background-color: #003f7f; /* Even darker blue when clicked */
        }



    </style>
</head>

<body style="background-image: linear-gradient(76deg, #ff5454, #ffb75d);">

    <div class="profile" style="background-image: linear-gradient(296deg, #d1ff7b, #ffe1e1);">
        <!-- "studentdata","cursession") -->
        <figure style="text-align:center;">
            <div>
                <h2 style="margin-top: 0px;color: crimson;">{{ $cursession->school }}</h2>
            </div>
            <img src="{{ $studentdata->photo }}" alt="Profile Picture" />
        </figure>
        <header style="text-align:center;">
            <h1>{{ $studentdata->name }}
                <small>Std.: {{ $studentdata->class }} - {{ $studentdata->section }}</small></h1>
        </header>
        <main style="display: block;">
            <dl>
                <dt>Date of birth</dt>
                <dd>{!! !empty($studentdata->dob)?$studentdata->dob:' &nbsp;' !!}</dd>
                <dt>Father's Name</dt>
                <dd>{!! !empty($studentdata->fathername)?$studentdata->fathername:' &nbsp;' !!}</dd>
                <dt>Mother's Name</dt>
                <dd>{!! !empty($studentdata->mothername)?$studentdata->mothername:' &nbsp;' !!}</dd>
                <dt>School Contact No.</dt>
                <dd><a
                        href="tel:{{ !empty($cursession->mobile)?$cursession->mobile:'' }}">{!!
                        !empty($cursession->mobile)?$cursession->mobile:' &nbsp;' !!}</a></dd>
                <dt>School Address</dt>
                <dd>{!! !empty($cursession->address)?$cursession->address:' &nbsp;' !!}</dd>
            </dl>
        </main>
        <div style="text-align: center;">
            <a class="button-sm" href="{{ request()->getSchemeAndHttpHost() }}/api/studentfeecard.php?servername={{$servername}}&studentid={{$accountid}}&companyid={{$companyid}}">Fees Card</a>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>

</html>
