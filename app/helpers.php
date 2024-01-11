<?php
if (!function_exists('generate_random_string')) {
    function generate_random_string($length = 5, $letters = true, $numbers = true, $hideSimilarCharacters = false) {
        $characters = $string = '';
        
        if ($letters) {
            $characters .= 'abcdefghijklmnopqrstuvwxyz';
        }

        if ($numbers) {
            $characters .= '0123456789';
        }

        if ($hideSimilarCharacters) {
            $similarCharacters = ['o', '0', 'l', 'i', '1'];
            $characters = str_replace($similarCharacters, '', $characters);
        }

        for ($i = 0; $i < $length; $i++) {
            $index = rand(0, strlen($characters) - 1);
            $string .= $characters[$index];
        }

        return $string;
    }
}
?>