import { useEffect, useMemo, useState } from "react";
import "./App.css";

const movies = [
  {
    id: 1,
    title: "Dune: Part Two",
    year: 2024,
    rating: 8.8,
    genre: ["Sci-Fi", "Adventure"],
    image: "https://picsum.photos/seed/dune/500/700",
    description:
      "Paul Atreides tiếp tục cuộc hành trình trên hành tinh Arrakis.",
  },
  {
    id: 2,
    title: "Oppenheimer",
    year: 2023,
    rating: 8.6,
    genre: ["Drama", "History"],
    image: "https://picsum.photos/seed/oppenheimer/500/700",
    description:
      "Câu chuyện về nhà khoa học đứng sau dự án phát triển bom nguyên tử.",
  },
  {
    id: 3,
    title: "Interstellar",
    year: 2014,
    rating: 9.0,
    genre: ["Sci-Fi", "Drama"],
    image: "https://picsum.photos/seed/interstellar/500/700",
    description:
      "Một nhóm phi hành gia vượt qua không gian để tìm ngôi nhà mới.",
  },
  {
    id: 4,
    title: "The Batman",
    year: 2022,
    rating: 8.1,
    genre: ["Action", "Crime"],
    image: "https://picsum.photos/seed/batman/500/700",
    description:
      "Batman điều tra những vụ án bí ẩn tại thành phố Gotham.",
  },
  {
    id: 5,
    title: "Spider-Man",
    year: 2021,
    rating: 8.4,
    genre: ["Action", "Adventure"],
    image: "https://picsum.photos/seed/spiderman/500/700",
    description:
      "Spider-Man đối mặt với những kẻ thù đến từ đa vũ trụ.",
  },
  {
    id: 6,
    title: "The Dark Knight",
    year: 2008,
    rating: 9.2,
    genre: ["Action", "Crime"],
    image: "https://picsum.photos/seed/darkknight/500/700",
    description:
      "Batman chiến đấu chống lại Joker tại Gotham City.",
  },
];

function Header({ search, setSearch, favorites }) {
  return (
    <header className="header">
      <div className="logo">
        Movie<span>Mate</span>
      </div>

      <nav>
        <a href="#home">Trang chủ</a>
        <a href="#movies">Phim</a>
        <a href="#favorites">
          Yêu thích ({favorites.length})
        </a>
      </nav>

      <input
        value={search}
        onChange={(event) => setSearch(event.target.value)}
        placeholder="Tìm kiếm phim..."
      />
    </header>
  );
}

function MovieCard({
  movie,
  favorites,
  onToggleFavorite,
  onSelectMovie,
}) {
  const isFavorite = favorites.includes(movie.id);

  return (
    <article className="movie-card">
      <img
        src={movie.image}
        alt={movie.title}
        onClick={() => onSelectMovie(movie)}
      />

      <div className="movie-content">
        <div className="movie-title-row">
          <h3>{movie.title}</h3>

          <button
            className={isFavorite ? "favorite active" : "favorite"}
            onClick={() => onToggleFavorite(movie.id)}
          >
            {isFavorite ? "♥" : "♡"}
          </button>
        </div>

        <div className="meta">
          <span>{movie.year}</span>
          <span>⭐ {movie.rating}</span>
        </div>

        <div className="genres">
          {movie.genre.map((item) => (
            <span key={item}>{item}</span>
          ))}
        </div>

        <p>{movie.description}</p>

        <button
          className="watch-button"
          onClick={() => onSelectMovie(movie)}
        >
          Xem chi tiết
        </button>
      </div>
    </article>
  );
}

function MovieModal({ movie, onClose }) {
  if (!movie) return null;

  return (
    <div className="modal-backdrop" onClick={onClose}>
      <div
        className="modal"
        onClick={(event) => event.stopPropagation()}
      >
        <button className="close-button" onClick={onClose}>
          ×
        </button>

        <img src={movie.image} alt={movie.title} />

        <section>
          <h2>{movie.title}</h2>

          <div className="modal-meta">
            <span>{movie.year}</span>
            <span>⭐ {movie.rating}</span>
          </div>

          <div className="genres">
            {movie.genre.map((genre) => (
              <span key={genre}>{genre}</span>
            ))}
          </div>

          <p>{movie.description}</p>

          <button className="play-button">
            ▶ Xem phim ngay
          </button>
        </section>
      </div>
    </div>
  );
}

function Stats({ movies, favorites }) {
  const averageRating = useMemo(() => {
    if (!movies.length) return 0;

    const total = movies.reduce(
      (sum, movie) => sum + movie.rating,
      0
    );

    return (total / movies.length).toFixed(1);
  }, [movies]);

  return (
    <section className="stats">
      <div className="stat">
        <strong>{movies.length}</strong>
        <span>Phim</span>
      </div>

      <div className="stat">
        <strong>{favorites.length}</strong>
        <span>Yêu thích</span>
      </div>

      <div className="stat">
        <strong>{averageRating}</strong>
        <span>Điểm trung bình</span>
      </div>
    </section>
  );
}

export default function App() {
  const [search, setSearch] = useState("");
  const [selectedGenre, setSelectedGenre] = useState("All");
  const [favorites, setFavorites] = useState(() => {
    const saved = localStorage.getItem("moviemate-favorites");

    return saved ? JSON.parse(saved) : [];
  });

  const [selectedMovie, setSelectedMovie] = useState(null);

  useEffect(() => {
    localStorage.setItem(
      "moviemate-favorites",
      JSON.stringify(favorites)
    );
  }, [favorites]);

  const genres = useMemo(() => {
    const allGenres = movies.flatMap((movie) => movie.genre);

    return ["All", ...new Set(allGenres)];
  }, []);

  const filteredMovies = useMemo(() => {
    return movies.filter((movie) => {
      const keyword = search.toLowerCase();

      const matchesSearch =
        movie.title.toLowerCase().includes(keyword) ||
        movie.description.toLowerCase().includes(keyword);

      const matchesGenre =
        selectedGenre === "All" ||
        movie.genre.includes(selectedGenre);

      return matchesSearch && matchesGenre;
    });
  }, [search, selectedGenre]);

  function toggleFavorite(id) {
    setFavorites((current) => {
      if (current.includes(id)) {
        return current.filter((movieId) => movieId !== id);
      }

      return [...current, id];
    });
  }

  function selectMovie(movie) {
    setSelectedMovie(movie);
  }

  return (
    <div className="app">
      <Header
        search={search}
        setSearch={setSearch}
        favorites={favorites}
      />

      <main>
        <section className="hero" id="home">
          <div>
            <p className="eyebrow">WELCOME TO MOVIEMATE</p>

            <h1>
              Khám phá
              <br />
              <span>thế giới điện ảnh</span>
            </h1>

            <p>
              Tìm kiếm những bộ phim yêu thích, lưu danh sách
              và khám phá các tác phẩm tuyệt vời.
            </p>

            <button
              className="hero-button"
              onClick={() =>
                document
                  .getElementById("movies")
                  ?.scrollIntoView({ behavior: "smooth" })
              }
            >
              Khám phá ngay
            </button>
          </div>
        </section>

        <Stats
          movies={movies}
          favorites={favorites}
        />

        <section className="filters">
          {genres.map((genre) => (
            <button
              key={genre}
              className={
                selectedGenre === genre ? "selected" : ""
              }
              onClick={() => setSelectedGenre(genre)}
            >
              {genre}
            </button>
          ))}
        </section>

        <section className="movies-section" id="movies">
          <div className="section-header">
            <div>
              <p className="eyebrow">MOVIES</p>
              <h2>Phim nổi bật</h2>
            </div>

            <span>{filteredMovies.length} kết quả</span>
          </div>

          {filteredMovies.length === 0 ? (
            <div className="empty">
              Không tìm thấy phim phù hợp.
            </div>
          ) : (
            <div className="movie-grid">
              {filteredMovies.map((movie) => (
                <MovieCard
                  key={movie.id}
                  movie={movie}
                  favorites={favorites}
                  onToggleFavorite={toggleFavorite}
                  onSelectMovie={selectMovie}
                />
              ))}
            </div>
          )}
        </section>

        <section className="favorites-section" id="favorites">
          <div className="section-header">
            <div>
              <p className="eyebrow">MY LIST</p>
              <h2>Danh sách yêu thích</h2>
            </div>
          </div>

          <div className="favorite-list">
            {favorites.length === 0 ? (
              <p>
                Bạn chưa thêm phim nào vào danh sách yêu thích.
              </p>
            ) : (
              movies
                .filter((movie) =>
                  favorites.includes(movie.id)
                )
                .map((movie) => (
                  <button
                    key={movie.id}
                    className="favorite-item"
                    onClick={() => selectMovie(movie)}
                  >
                    <img
                      src={movie.image}
                      alt={movie.title}
                    />

                    <span>
                      {movie.title}
                      <small>
                        {movie.year} · ⭐ {movie.rating}
                      </small>
                    </span>
                  </button>
                ))
            )}
          </div>
        </section>
      </main>

      <footer>
        <strong>MovieMate</strong>
        <p>Discover. Watch. Enjoy.</p>
      </footer>

      <MovieModal
        movie={selectedMovie}
        onClose={() => setSelectedMovie(null)}
      />
    </div>
  );
}