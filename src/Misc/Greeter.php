<?php

namespace tthe\Bagatelle\Misc;

class Greeter
{
    public function greet(): string
    {
        $greetings = [
            'Hello!', 'Hi!', 'Hey!', 'Yo!', 'Hiya!',
            "How's everything?", 'How are you?', "How's it going?", "What's up?", 'Howdy!',
            'Greetings!', 'Welcome!', 'Nice to see you!', 'Long time no see!', 'How have you been?',
            'Good to see you!', 'Pleased to meet you!', 'How do you do?', 'Hey there!', "What's new?",
        ];

        return $greetings[array_rand($greetings)];
    }
}