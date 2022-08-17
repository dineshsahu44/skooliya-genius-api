
//php artisan passport:install//run on serve to install
for passport token auth //https://github.com/anil-sidhu/laravel-passport-poc
replace HasApiTokens //use Laravel\Passport\HasApiTokens; //for token work properly
//E:\NewProject\skooliya-genius-api\app\Http\Middleware\Authenticate.php here we replace return route('login'); to return route('loginapi.php');