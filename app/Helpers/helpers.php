<?php



// send transactional mail
if (!function_exists("tribearcSendMail")) {
    function tribearcSendMail($subject, $content, $mails, $website = 'RexMFBank', $from_email = 'hello@rexmfbank.com')
    {
        $curl = curl_init();
        curl_setopt(
            $curl,
            CURLOPT_URL,
            "https://mail.tribearc.com/api/campaigns/send_now.php"
        );
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); //
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); //
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST"); //
        curl_setopt($curl, CURLOPT_POSTFIELDS, [
            "api_key" => env("TribearcMail_API_KEY"),
            "from_name" =>   $website ?? env("APP_NAME"),
            "from_email" => $from_email ?? env("MAIL_FROM_ADDRESS"),
            "reply_to" =>  $from_email  ?? env("MAIL_FROM_ADDRESS"),
            "subject" => $subject,
            "html_text" => $content,
            "track_opens" => "1",
            "track_clicks" => "1",
            "send_campaign" => "1",
            "json" => "1",
            "emails" => $mails,
            "business_address" => "11, Adekoya Estate, Off College Road, Ogba Lagos",
            "business_name" => env("APP_NAME"),
            "bcc" => "eopeyemi.tv@gmail.com"
        ]);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "Api-Token: Yo3UvyRyezbQewabuuWz",
        ]);

        $response = curl_exec($curl);
        $res = json_decode($response);
        curl_close($curl);
        return $res;
    }
}