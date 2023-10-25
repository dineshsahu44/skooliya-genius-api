<html>
    <body>
        <form method="Post" action="/parse-log" enctype="multipart/form-data">  
        @csrf  
            <input type="file" name="log_file" type="txt">
            <input type="submit" value="ok">
        </form>
    </body>
</html>