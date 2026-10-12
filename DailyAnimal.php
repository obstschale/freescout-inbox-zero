<?php

namespace Modules\InboxZero;

class DailyAnimal
{
    // Keep this order stable: translations and user preferences must not change the daily animal.
    const ANIMALS = [
        'fox', 'snail', 'raccoon', 'otter', 'capybara',
        'cat', 'sloth', 'penguin', 'hamster', 'owl',
        'dog', 'hedgehog', 'beaver', 'duck', 'parrot',
        'octopus', 'mouse', 'tortoise', 'pigeon', 'alpaca',
        'panda', 'koala', 'rabbit', 'squirrel', 'badger',
        'deer', 'moose', 'bear', 'polar-bear', 'red-panda',
        'seal', 'walrus', 'dolphin', 'whale', 'manatee',
        'sea-lion', 'puffin', 'pelican', 'flamingo', 'swan',
        'frog', 'axolotl', 'chameleon', 'gecko', 'iguana',
        'crocodile', 'snake', 'turtle', 'crab', 'lobster',
        'butterfly', 'bee', 'ladybird', 'dragonfly', 'grasshopper',
        'ant', 'beetle', 'caterpillar', 'moth', 'spider',
        'elephant', 'giraffe', 'zebra', 'hippo', 'rhino',
        'lion', 'tiger', 'cheetah', 'meerkat', 'lemur',
        'gorilla', 'orangutan', 'gibbon', 'kangaroo', 'wombat',
        'quokka', 'platypus', 'echidna', 'tasmanian-devil', 'kiwi',
        'sheep', 'goat', 'cow', 'pig', 'horse',
        'donkey', 'chicken', 'rooster', 'turkey', 'goose',
        'bat', 'ferret', 'chinchilla', 'guinea-pig', 'mole',
        'shrew', 'opossum', 'armadillo', 'anteater', 'pangolin',
    ];

    public static function forDate(\DateTimeInterface $instant, $timezone)
    {
        $localDate = (new \DateTimeImmutable('@'.$instant->getTimestamp()))
            ->setTimezone(new \DateTimeZone($timezone));

        // Count calendar days, not elapsed hours: DST must not shift the rotation.
        $calendarDate = new \DateTimeImmutable($localDate->format('Y-m-d'), new \DateTimeZone('UTC'));
        $epoch = new \DateTimeImmutable('2026-01-01', new \DateTimeZone('UTC'));
        $days = (int) $epoch->diff($calendarDate)->format('%r%a');
        $count = count(self::ANIMALS);

        return self::ANIMALS[(($days % $count) + $count) % $count];
    }
}
