<?php

defined('SYSPATH') or die('No direct script access.');

/**
 * Mail sender.
 * 
 * @package Mail
 * @author Guillaume Poirier-Morency <guillaumepoiriermorency@gmail.com>
 * @copyright (c) 2013, Hète.ca Inc.
 */
abstract class Kohana_Mail_Sender {

    /**
     * Default sender. 
     * 
     * @var string 
     */
    public static $default = "Sendmail";

    /**
     * Return an instance of the specified sender.
     * 
     * @return Mail_Sender 
     */
    public static function factory($name = NULL) {

        if ($name === NULL) {
            $name = static::$default;
        }

        $class = "Mail_Sender_$name";

        return new $class();
    }

    public static function encode($value) {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    /**
     * Current configuration.
     * @var array 
     */
    private $_config;

    private function __construct() {
        // Load the corresponding configuration
        $this->_config = Kohana::$config->load("mail.sender");
    }

    /**
     * Generate headers for the specified receiver. $receiver is not yet used,
     * but will be used to u
     * 
     * @param Mail_Receiver $receiver
     * @return array
     */
    public function generate_headers() {
        return array(
            "From" => static::encode($this->config("from.name")) . " <" . $this->config("from.email") . ">",
            "Date" => Date::formatted_time("now"),
            "Content-type" => "text/html; charset=UTF-8",
            "MIME-Version" => "1.0"
        );
    }

    /**
     * Content generation function.
     * 
     * @todo améliorer l'implémentation pour la génération du contenu.
     * @return View
     */
    public function generate_content(Mail_Receiver $receiver, $view, array $parameters = NULL) {

        if ($parameters === NULL) {
            $parameters = array();
        }

        $parameters["receiver"] = $receiver;

        $template = View::factory("mail/layout/template", $parameters);
        $template->header = View::factory("mail/layout/header", $parameters);
        $template->content = View::factory($view, $parameters);
        $template->footer = View::factory("mail/layout/footer", $parameters);

        return $template;
    }

    public function config($path = NULL, $default = NULL, $delimiter = NULL) {

        if ($path === NULL) {
            return $this->_config;
        }

        return Arr::path($this->_config, $path, $default, $delimiter);
    }

    /**
     * Envoie un courriel à tous les utilisateurs de la variable $receivers.
     * 
     * @param Mail_Receiver|Traversable|array $receivers set of Mail_Receiver or
     * a Mail_Receiver object.
     * @param View $view content to be sent.
     * @param array $parameters view's parameters.
     * @param string $subject is the subject of the mail. It is UTF-8 encoded, 
     * so you can use accents and other characters.
     * @param array $headers is an array of mail headers.
     * @param boolean $check_if_subscribed verifies if the receiver is 
     * subscribed to the mail.
     * @return boolean false si au moins un envoie échoue.
     */
    public function send($receivers, $view, array $parameters = NULL, $subject = NULL, array $headers = NULL, $check_if_subscribed = TRUE) {

        if ($subject === NULL) {
            $subject = $this->config("subject");
        }

        if ($headers === NULL) {
            $headers = array();
        }

        $result = true;

        if (!($receivers instanceof Traversable or Arr::is_array($receivers))) {
            $receivers = array($receivers);
        }

        foreach ($receivers as $key => $receiver) {

            if (is_string($receiver) && Valid::email($email = $receiver)) {
                $receiver = Model::factory("Mail_Receiver");
                $receiver->email = $email;
                // Checking if key is a name
                if (is_string($key)) {
                    $receiver->name = $key;
                }
            }

            if (!$receiver instanceof Mail_Receiver) {
                throw new Kohana_Exception("Receiver must be an instance of Mail_Receiver");
            }

            // On vérifie si l'utilisateur est abonné
            if ($check_if_subscribed AND !$receiver->receiver_subscribed($view)) {
                continue;
            }

            // Update receiver
            $parameters["receiver"] = $receiver;

            // Merge headers
            $_headers = Arr::merge($this->generate_headers(), $headers);

            // Regenerate content
            $content = $this->generate_content($receiver, $view, $parameters, $subject);

            $mail = new Model_Mail($receiver, $content, $subject, $_headers);

            $result = $result AND $this->_send($mail);
        }

        // Cumulated result
        return $result;
    }

    /**
     * Implemented by the sender.
     * 
     * @param Model_Mail $mail  
     * @return boolean true if sending is successful, false otherwise.
     */
    public abstract function _send(Model_Mail $mail);
}

?>
