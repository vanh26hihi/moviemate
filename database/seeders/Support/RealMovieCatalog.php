<?php

namespace Database\Seeders\Support;

use App\Models\Movie;

final class RealMovieCatalog
{
    public const PROVIDER = 'tmdb';

    public const MEDIA_HOST = 'media.themoviedb.org';

    /**
     * A verified, deterministic snapshot prepared from public TMDB movie pages.
     * Routine seeds and automated tests deliberately make no network requests.
     *
     * @return list<array{provider: string, provider_id: int, title: string, description: string, duration: int, age_rating: string, release_date: string, status: string, genres: list<string>, poster: string, cover_image: string}>
     */
    public static function movies(): array
    {
        return [
            self::movie(969681, 'Spider-Man: Brand New Day', 'Peter Parker balances university life with fighting crime full-time as Spider-Man while a mysterious force changes the rules around him.', 145, 'T13', '2026-07-31', Movie::STATUS_NOW_SHOWING, ['Science Fiction', 'Action', 'Adventure'], 'iPOn6DinuVyLY17YM9mKuPofV08.jpg', 'qeQJx07rK2xm8SD2sJxFKhE7gs0.jpg'),
            self::movie(1368337, 'The Odyssey', 'Odysseus undertakes a dangerous journey home after the Trojan War and faces mythic creatures, gods, and trials along the way.', 173, 'T16', '2026-07-17', Movie::STATUS_NOW_SHOWING, ['Adventure', 'Action', 'Fantasy'], '5rhTDKUhPYvpdQIijFIs5VoWsON.jpg', 'RMXG8myu1aGlNUsRjtxzmpdMK0.jpg'),
            self::movie(1108427, 'Moana', 'A young wayfinder answers the ocean\'s call and journeys beyond the reef to help restore balance to her island home.', 115, 'P', '2026-07-10', Movie::STATUS_NOW_SHOWING, ['Family', 'Fantasy', 'Comedy', 'Adventure'], 'zKVgiv5qHCvCLT4A2ymJi5QeXDH.jpg', 'c6BPbkO5Npt1OdwttAxCFo06wtH.jpg'),
            self::movie(1315772, 'Minions & Monsters', 'The Minions stumble into a new comic adventure filled with monsters, mayhem, and an unexpected family mission.', 90, 'P', '2026-07-01', Movie::STATUS_NOW_SHOWING, ['Adventure', 'Animation', 'Comedy', 'Family', 'Fantasy'], 'nz7i42yhLIJ4ve9JKgM6NthoLHO.jpg', 'kkcwhgSFd81QDlXo8ytrpHPQjhy.jpg'),
            self::movie(1212763, 'Evil Dead Burn', 'A new nightmare from the Evil Dead universe unleashes demonic terror and a desperate fight for survival.', 110, 'T18', '2026-07-10', Movie::STATUS_NOW_SHOWING, ['Horror'], 'uRxrNXQWkHoENm3nwVOZDYSCx2F.jpg', 'biwEwIkjZhMUfXzz59bpeDzwYB6.jpg'),
            self::movie(1564614, 'Leviticus', 'Two young men confront a supernatural force as forbidden love and buried fears draw them into escalating horror.', 88, 'T18', '2026-07-03', Movie::STATUS_NOW_SHOWING, ['Horror', 'Romance'], 'gnAsZvBygplNpp8PtjoTEYv3VPB.jpg', '7y8zWGEjs7tresw4Hzkkf4TdkcL.jpg'),
            self::movie(1383731, 'Protector', 'A determined protector is pulled into a violent conspiracy and must risk everything to keep a vulnerable target alive.', 90, 'T18', '2026-07-17', Movie::STATUS_NOW_SHOWING, ['Action', 'Thriller'], 'icOZpnGuH9YrEaW3wrw5GJaXGih.jpg', 'vpuPY4UziUCxv7gYYoaZQ3LX7to.jpg'),
            self::movie(1545621, 'Detective Conan: Fallen Angel of the Highway', 'Conan investigates a highway incident that develops into a complex case involving crime, danger, and a hidden adversary.', 109, 'P', '2026-07-24', Movie::STATUS_NOW_SHOWING, ['Animation', 'Action', 'Mystery', 'Crime'], 'tqlOfb1ekyVYpDumiL9MsK6uirw.jpg', 'zT5S4GNs8Eu2gIkGORoN6yC1uE2.jpg'),
            self::movie(1671548, 'Dear You', 'A family story unfolds through affection, misunderstandings, and the choices that reconnect people who have grown apart.', 118, 'P', '2026-08-07', Movie::STATUS_NOW_SHOWING, ['Drama', 'Family', 'Comedy'], 'rjmhzdVS3Ia535pFawju857e2Na.jpg', 'AwmlL79nKTcX5tzAhyoV298xXlz.jpg'),
            self::movie(1417285, 'The Stain', 'A disturbing presence stains a relationship with obsession, fear, and consequences that become impossible to escape.', 105, 'T18', '2026-07-10', Movie::STATUS_NOW_SHOWING, ['Horror', 'Thriller', 'Romance'], 'vPxVvwMduxySggqEyHpwQNtjbx6.jpg', 's9kbcVd0PQCw2JPKo9C84ChP2x.jpg'),
            self::movie(1553969, 'Sheep in the Box', 'A family encounters a mysterious human-like robot whose arrival forces them to reconsider memory, grief, and what makes a person real.', 126, 'P', '2026-07-03', Movie::STATUS_NOW_SHOWING, ['Drama', 'Science Fiction'], '9rOCIYYT8Q76FHgl3Nm5ofuc6TQ.jpg', 'iZCdWpGqKQaAdhysW8gYPTdCP6A.jpg'),
            self::movie(1251900, 'The Shrine', 'A search connected to a remote shrine leads into an unsettling mystery where old beliefs and supernatural danger collide.', 95, 'T16', '2026-07-03', Movie::STATUS_NOW_SHOWING, ['Horror', 'Mystery'], 'grq3eAFw6D0iFB1xfBA9GPbNjeD.jpg', '8KzGsh6LTZCbYg4UujDhYqlmVzg.jpg'),
            self::movie(1418308, 'Ghost Board', 'A group uses a spirit board and awakens a deadly presence that turns their curiosity into a fight to survive.', 125, 'T16', '2026-07-03', Movie::STATUS_NOW_SHOWING, ['Horror', 'Thriller'], 'xVjDFOKoZuPOv1m4Z7NJpQ1gbfF.jpg', 'nGbiKpQ4O9TQV9hpathwAEh18V4.jpg'),
            self::movie(1419036, 'Kijsada Paradise', 'Visitors to an isolated paradise encounter a haunting past and a supernatural threat hidden behind its beauty.', 113, 'T16', '2026-07-24', Movie::STATUS_NOW_SHOWING, ['Horror'], 'flWf8cNQrlw1PXXW7uzPZPGRDHx.jpg', 'x54Apuuj38C3aPv82BH34TQt8Um.jpg'),
            self::movie(467914, 'The Land of Sometimes', 'Twins travel to a magical island where every day brings a new character, song, and imaginative adventure.', 93, 'P', '2026-07-31', Movie::STATUS_NOW_SHOWING, ['Animation', 'Fantasy', 'Family', 'Adventure'], 'uEZAx4Rk42hv8bfrXC9pyQPWErw.jpg', 'nHP6HaHRZ8GHveLH5VVJSFFQ3S.jpg'),
            self::movie(1739748, 'Beyond The Sky', 'A brief encounter opens an intimate story about longing, connection, and looking beyond the limits people place around themselves.', 10, 'P', '2026-07-25', Movie::STATUS_NOW_SHOWING, ['Drama', 'Romance'], 'n1HrReIzov2K6mUm2iP8oIIoO3Q.jpg', 'hBQM83bU6CxOdVHSSqlIZ7eGMGD.jpg'),

            self::movie(1101383, 'The End of Oak Street', 'A mystery on Oak Street draws ordinary people into a science-fiction conspiracy where nothing about their neighborhood is what it seems.', 100, 'T13', '2026-08-14', Movie::STATUS_COMING_SOON, ['Science Fiction', 'Mystery', 'Thriller'], '3SifFCwwFzXdU1Ew0nA4Z92Bs15.jpg', 'b9q9VmbXDvJmTziRqkwdEmFdwhr.jpg'),
            self::movie(1185806, 'PAW Patrol: The Dino Movie', 'The PAW Patrol enters a dinosaur-sized rescue adventure and works together to protect Adventure Bay.', 88, 'P', '2026-08-14', Movie::STATUS_COMING_SOON, ['Adventure', 'Animation', 'Comedy', 'Family'], 'cxXSF4aY5N2cOGwaga5OnpPFHzk.jpg', 'upNeU9FNGAvdhnoThvv64Z0MWKn.jpg'),
            self::movie(1291595, 'Insidious: Out of the Further', 'A new chapter opens in The Further as a family faces the lingering supernatural evil connected to the Lambert legacy.', 106, 'T13', '2026-08-21', Movie::STATUS_COMING_SOON, ['Horror', 'Thriller'], '4tTrW9dXCByS5wt2pXVWb58zNjz.jpg', 'hD8y787ciNWQ2bn396YrSsOIzdN.jpg'),
            self::movie(1383154, 'The Eyes', 'A tense encounter turns into a dangerous ordeal in which watching and being watched have deadly consequences.', 106, 'T16', '2026-08-14', Movie::STATUS_COMING_SOON, ['Thriller', 'Horror'], 'yH2sGLdQejqf3Zk8KDuoDa5gr6E.jpg', '75S750SAsppSiQc0S3EuSH0K77O.jpg'),
            self::movie(1623568, 'Agito: Superpower War', 'Young heroes with extraordinary abilities are swept into a conflict that will decide how their powers shape the world.', 97, 'T13', '2026-08-14', Movie::STATUS_COMING_SOON, ['Action', 'Adventure', 'Science Fiction'], 'jPni7Oz12gZcCMnXsyqgcbBu2Pj.jpg', 'seiGSDA3rcgKIAcom0nZzXTqogH.jpg'),
            self::movie(1469229, 'The Djinn\'s Curse 2', 'The curse returns with a more dangerous supernatural force and new victims struggling to break its hold.', 97, 'T16', '2026-08-21', Movie::STATUS_COMING_SOON, ['Horror', 'Thriller'], '76BkBITF6zdHWdtEsWePhekA5nw.jpg', 'beEB5DlK30LneSXuaSzEJM48O40.jpg'),
            self::movie(1554807, 'Spirit Guardians: The Last Secret of the First Emperor', 'Guardians race to uncover the First Emperor\'s final secret as war, ancient mystery, and supernatural forces converge.', 151, 'P', '2026-08-28', Movie::STATUS_COMING_SOON, ['Action', 'Drama', 'War', 'Thriller', 'Mystery', 'Fantasy', 'Adventure'], 'uaXDal48MG0sTGs2XU6L1wuVaUO.jpg', '2H8hOTgw5Rrbl70CXi3hPKfzhhn.jpg'),
        ];
    }

    /** @return list<string> */
    public static function slugs(string $status): array
    {
        return array_values(array_map(
            fn (array $movie): string => str($movie['title'])->slug()->toString(),
            array_filter(self::movies(), fn (array $movie): bool => $movie['status'] === $status),
        ));
    }

    /**
     * @param  list<string>  $genres
     * @return array{provider: string, provider_id: int, title: string, description: string, duration: int, age_rating: string, release_date: string, status: string, genres: list<string>, poster: string, cover_image: string}
     */
    private static function movie(int $providerId, string $title, string $description, int $duration, string $ageRating, string $releaseDate, string $status, array $genres, string $poster, string $backdrop): array
    {
        return [
            'provider' => self::PROVIDER,
            'provider_id' => $providerId,
            'title' => $title,
            'description' => $description,
            'duration' => $duration,
            'age_rating' => $ageRating,
            'release_date' => $releaseDate,
            'status' => $status,
            'genres' => $genres,
            'poster' => "https://media.themoviedb.org/t/p/w500/{$poster}",
            'cover_image' => "https://media.themoviedb.org/t/p/w1280/{$backdrop}",
        ];
    }
}
