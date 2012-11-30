<?php

class Kohana_Model_Mail extends Kohana_Model_Validation {

    /**
     *
     * @var Model_User 
     */
    public $receiver;
    public $content, $headers,
            $subject;

    /**
     * 
     * @param Model_User $receiver people who will receive this mail.
     * @param type $subject mail's subject.
     * @param View $content mail's content stored in a view.
     * @param array $headers headers
     */
    public function __construct(Model_User $receiver, $subject, View $content, array $headers = NULL) {

        if ($headers === NULL) {
            $headers = array();
        }

        $basic_headers = array(
            "To" => $receiver->nom_complet() . " <$receiver->email>",
            "Subject" => $subject,
            "Date" => date(Date::$timestamp_format),
            "Content-type" => 'text/html; charset=UTF-8',
            "MIME-Version" => 1.0
        );

        $this->receiver = $receiver;
        $this->subject = $subject;
        $this->content = $content;
        $this->headers = Arr::merge($basic_headers, $headers);
    }

    /**
     * 
     * @return type
     */
    private function generate_headers() {
        $output = array();
        foreach ($this->headers as $key => $value) {
            $output[] = "$key: $value";
        }
        return implode("\r\n", $output);
    }

    /**
     * Envoie le mail au receveur.
     * @param boolean $async si true, le mail sera stocké de façon asynchrome.
     * @return boolean le résultat de la fonction mail.
     */
    public function send($async = FALSE) {
        if ($async) {
            return Mail_Sender::instance()->push($this);
        }
        return mail($this->receiver->email, '=?UTF-8?B?' . base64_encode($this->subject) . '?=', $this->content->render(), $this->generate_headers());
    }

}

?>