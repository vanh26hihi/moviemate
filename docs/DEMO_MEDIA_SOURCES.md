# Nguồn ảnh demo phim

MovieMate dùng poster và backdrop từ The Movie Database (TMDB) cho dữ liệu trình diễn học thuật, phi thương mại. Các bản ảnh dùng trong môi trường demo được lưu cục bộ để buổi bảo vệ không phụ thuộc kết nối tới CDN.

Nguồn danh mục và mã phim được cố định tại `database/seeders/Support/RealMovieCatalog.php`. Tài sản tương ứng nằm trong `database/seeders/assets/movie-media` và được `DemoMovieMediaSeeder` sao chép sang public storage khi chạy seed ở môi trường local.

This product uses the TMDB API but is not endorsed or certified by TMDB.

- Website: https://www.themoviedb.org
- Image API documentation: https://developer.themoviedb.org/reference/movie-images
- Attribution requirements: https://developer.themoviedb.org/docs/faq
