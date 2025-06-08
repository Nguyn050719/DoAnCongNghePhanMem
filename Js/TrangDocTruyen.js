
// Các hàm tiện ích
function getUrlParam(name) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(name);
}

const API_BASE_URL = 'http://localhost/Webtruyen_3/PHP';
let currentUser = null; // Sẽ lưu thông tin người dùng đã đăng nhập (userId, username, role)
let currentComicTitle = ''; // Thêm biến global mới cho tên truyện
let currentChapterNumber;
let totalChapters = 0;
let currentChapterTitle = '';
let currentChapterId = null; // THÊM BIẾN TOÀN CỤC NÀY

// --- Logic Xử lý lỗi & Timeout ---
const errorMessage = document.getElementById('error-message');
let loadTimeout;
let brokenImageCount = 0;
const MAX_BROKEN_IMAGES_BEFORE_ERROR = 1;
let hasCriticalErrorOccurred = false;

function showErrorMessage() {
    if (errorMessage && errorMessage.style.display !== 'block') {
        errorMessage.style.display = 'block';
        console.log('SHOWING ERROR MESSAGE: Triggered by timeout or broken image.');
        hasCriticalErrorOccurred = true;
    }
}

function hideErrorMessage() {
    if (errorMessage && !hasCriticalErrorOccurred) {
        errorMessage.style.display = 'none';
        brokenImageCount = 0;
        console.log('HIDING ERROR MESSAGE: Triggered.');
    } else if (errorMessage) {
        console.log('HIDE ERROR MESSAGE BLOCKED: Critical error occurred. Message remains visible.');
    }
}

// --- CÁC HÀM XỬ LÝ TRẠNG THÁI ĐĂNG NHẬP MỚI/CẬP NHẬT ---

/**
 * Hàm lấy thông tin profile người dùng từ server.
 * Cập nhật biến currentUser và localStorage nếu thành công.
 * Đây là hàm bù đắp cho việc DangNhap.html không lưu đúng.
 */
async function fetchUserProfileFromServer() {
    try {
        const response = await fetch(`${API_BASE_URL}/getProfile.php`);
        const data = await response.json();

        if (data.success) {
            currentUser = {
                USES_ID: data.usesId, // Cần đảm bảo getProfile.php trả về 'usesId'
                USERNAME: data.username,
                AVATAR: data.avatar,
                COVER_IMAGE: data.cover,
                BIO: data.bio,
                DISPLAY_NAME: data.displayName,
                ROLE: data.role
            };
            // Lưu đối tượng người dùng đã fetch được vào localStorage để sử dụng cho lần sau
            localStorage.setItem('loggedInUser', JSON.stringify(currentUser));
            localStorage.setItem('role', currentUser.ROLE); // Cập nhật cả role

            console.log("Đã lấy thành công profile từ server và cập nhật localStorage.");
        } else {
            console.warn("Không thể lấy profile từ server (có thể do chưa đăng nhập hoặc session hết hạn):", data.message);
            currentUser = null;
            localStorage.removeItem('loggedInUser'); // Xóa dữ liệu có thể không hợp lệ
            localStorage.removeItem('role');
        }
    } catch (error) {
        console.error("Lỗi khi gọi API getProfile.php:", error);
        currentUser = null;
        localStorage.removeItem('loggedInUser');
        localStorage.removeItem('role');
    }
}

/**
 * Kiểm tra trạng thái đăng nhập của người dùng.
 * Sẽ luôn thử lấy từ server nếu loggedInUser không có.
 */
async function checkLoginStatus() {
    const storedUser = localStorage.getItem('loggedInUser');
    const storedRole = localStorage.getItem('role'); // Sẽ tồn tại nếu DangNhap.html lưu nó

    // Nếu loggedInUser đã có và hợp lệ trong localStorage
    if (storedUser) {
        try {
            currentUser = JSON.parse(storedUser);
            // Kiểm tra cấu trúc cơ bản của currentUser, đặc biệt là USES_ID
            if (!currentUser || !currentUser.USES_ID) {
                console.warn("Dữ liệu người dùng trong localStorage không hợp lệ hoặc thiếu USES_ID. Đang thử lấy lại profile từ server.");
                await fetchUserProfileFromServer();
            }
        } catch (e) {
            console.error("Lỗi khi phân tích dữ liệu người dùng từ localStorage:", e);
            currentUser = null;
            localStorage.removeItem('loggedInUser');
            localStorage.removeItem('role');
            await fetchUserProfileFromServer(); // Thử lấy lại nếu parse lỗi
        }
    } 
    
    // Nếu loggedInUser vẫn là null (do DangNhap.html không lưu đúng)
    // Hoặc nếu quá trình parse/fetchUserProfileFromServer trước đó thất bại
    // Và nếu có dấu hiệu người dùng đã đăng nhập (ví dụ: role vẫn còn trong localStorage)
    if (currentUser === null) {
        console.warn("currentUser rỗng. Đang cố gắng lấy profile từ server để xác định trạng thái đăng nhập.");
        await fetchUserProfileFromServer(); // Luôn thử fetch từ server
    }

    // Cuối cùng, cập nhật UI dựa trên trạng thái currentUser
    updateUIForLoginStatus();
}


/**
 * Cập nhật giao diện người dùng dựa trên trạng thái đăng nhập của currentUser.
 */
function updateUIForLoginStatus() {
    // const loginIcon = document.getElementById('login-icon'); // Không còn cần thiết để điều khiển display của login-icon
    // const avatarImg = document.getElementById('avatar-img'); // Đây là id cũ, không dùng nữa
    const loginName = document.getElementById('login-name'); // Nơi hiển thị tên/tên đăng nhập
    const profileLink = document.getElementById('profile-link'); // Thẻ <a> để liên kết đến trang cá nhân
    const avatarTopRight = document.getElementById('avatarTopRight'); // Lấy thẻ img có id="avatarTopRight"

    if (currentUser) {
        if (avatarTopRight) {
            avatarTopRight.src = currentUser.AVATAR || '../Image/avatar.jpg'; // Cập nhật src
            avatarTopRight.style.display = 'block'; // HIỂN THỊ ẢNH
        }
        if (loginName) loginName.textContent = currentUser.DISPLAY_NAME || currentUser.USERNAME;
        
        if (profileLink) {
            if (currentUser.ROLE === 'admin') {
                profileLink.href = 'TrangAdmin.html';
            } else {
                profileLink.href = 'TrangCaNhan.html';
            }
        }
        // if (loginIcon) loginIcon.style.display = 'block'; // Dòng này không cần nếu avatarTopRight là đủ

        const commentInputArea = document.querySelector('.comment-input-area');
        if (commentInputArea) {
            commentInputArea.style.display = 'flex';
        }
    } else {
        if (avatarTopRight) {
            avatarTopRight.src = '../Image/avatar.jpg'; // Đặt ảnh mặc định
            avatarTopRight.style.display = 'none'; // ẨN ẢNH KHI CHƯA ĐĂNG NHẬP (hoặc theo CSS mặc định)
        }
        if (loginName) loginName.textContent = 'Đăng nhập';
        if (profileLink) profileLink.href = 'DangNhap.html';
        
        const commentInputArea = document.querySelector('.comment-input-area');
        if (commentInputArea) {
            commentInputArea.style.display = 'none';
        }
    }
}
function logoutUser() {
    localStorage.removeItem('loggedInUser');
    localStorage.removeItem('role');
    localStorage.removeItem('avatar');
    currentUser = null;
    alert('Bạn đã đăng xuất.');
    const loginIconElement = document.getElementById('avatarTopRight');
    if (loginIconElement) {
        loginIconElement.src = '../Image/avatar.jpg';
        loginIconElement.style.display = "block";
    }
    const userMenuElement = document.getElementById('user-menu');
    if (userMenuElement) {
        userMenuElement.style.display = 'none';
    }
    if (typeof displayComments === 'function') {
        displayComments();
    }
    updateRoleBasedLinks(); // Cập nhật lại các liên kết khi đăng xuất
    window.location.href = "DangNhap.html";
}

function hideLoginModal() {
    console.log('Hiding login modal...');
    // Thêm logic để ẩn modal của bạn tại đây, ví dụ:
    // document.getElementById('loginModal').style.display = 'none';
    // Hoặc remove class 'active' khỏi modal
}

function updateLoginIcon() {
    const loginIcon = document.getElementById('avatarTopRight');

    if (!loginIcon) {
        console.warn("Element with ID 'avatarTopRight' not found.");
        return;
    }

    // Luôn fetch profile để lấy avatar
    fetch(`${API_BASE_URL}/getProfile.php`)
        .then(response => {
            if (!response.ok) {
                // Xử lý lỗi HTTP (ví dụ: 404, 500)
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            let finalAvatarSrc = '../Image/avatar.jpg'; // Mặc định là ảnh này

            // Nếu API trả về thành công và có dữ liệu avatar
            if (data.success && data.avatar) {
                // Lấy trực tiếp giá trị 'avatar' từ phản hồi JSON
                // getProfile.php đã thêm '../Image/' rồi nên không cần thêm nữa.
                finalAvatarSrc = data.avatar; 
                console.log("Avatar nhận được từ server:", finalAvatarSrc);
            } else {
                console.warn('Failed to load user avatar or no avatar found from server. Using default.');
            }
            
            loginIcon.src = finalAvatarSrc;
            loginIcon.style.display = 'block';
        })
        .catch(error => {
            console.error('Error fetching user profile:', error);
            // Trong trường hợp có lỗi kết nối hoặc lỗi fetch, quay về ảnh đại diện mặc định
            loginIcon.src = '../Image/avatar.jpg';
            loginIcon.style.display = 'block';
        });
}

// Hàm mới để cập nhật hiển thị số chương và tên chương
function updateChapterNumbersDisplay() {
    const chapterNumberDisplay = document.getElementById('currentChapterNumber');
    if (chapterNumberDisplay) {
        chapterNumberDisplay.textContent = `Chap ${currentChapterNumber}`;
    } else {
        console.warn("Element with ID 'currentChapterNumber' not found. Cannot display current chapter number.");
    }
}

// CHỈNH SỬA LẠI HÀM demoLoginRegister NÀY (chỉ phần đăng nhập)
async function demoLoginRegister(type, username, password, email = null) {
    let endpoint = '';
    let fullUrl = '';

    const formData = new URLSearchParams();
    formData.append('username', username);
    formData.append('password', password);

    if (type === 'register') {
        endpoint = '/register.php';
        if (email) {
            formData.append('email', email);
        }
    } else if (type === 'login') {
        endpoint = '/login.php';
    } else {
        alert('Loại hành động không hợp lệ.');
        return;
    }

    if (typeof API_BASE_URL === 'undefined') {
        console.error('API_BASE_URL is not defined. Please ensure it is available in scope.');
        alert('Lỗi cấu hình: Không tìm thấy địa chỉ API.');
        return;
    }
    fullUrl = `${API_BASE_URL}${endpoint}`;

    try {
        const response = await fetch(fullUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData.toString()
        });

        const responseText = await response.text();

        if (response.ok) {
            if (type === 'login') {
                const parts = responseText.split('|');
                if (parts[0] === 'Đăng nhập thành công!' && parts.length >= 7) {
                    currentUser = {
                        USES_ID: parts[1],
                        USERNAME: parts[2],
                        AVATAR: parts[3],
                        COVER_IMAGE: parts[4],
                        BIO: parts[5],
                        DISPLAY_NAME: parts[6],
                        ROLE: parts[7]
                    };
                    localStorage.setItem('loggedInUser', JSON.stringify(currentUser));
                    localStorage.setItem('role', currentUser.ROLE);
                    // localStorage.setItem('avatar', currentUser.AVATAR); // Dòng này không cần thiết nếu updateLoginIcon luôn fetch

                    updateLoginIcon(); // Cập nhật icon sau khi đăng nhập

                    alert('Đăng nhập thành công!');
                    hideLoginModal();

                    if (typeof displayComments === 'function') {
                        displayComments();
                    }
                    // Cập nhật lại các liên kết dựa trên vai trò sau khi đăng nhập
                    updateRoleBasedLinks();
                } else {
                    alert('Lỗi đăng nhập: ' + responseText);
                    console.error('Lỗi định dạng phản hồi từ login.php:', responseText);
                }
            } else {
                if (responseText === 'Đăng ký thành công!') {
                    alert(responseText);
                    hideLoginModal();
                } else {
                    alert('Lỗi đăng ký: ' + responseText);
                    console.error('Lỗi đăng ký:', responseText);
                }
            }
        } else {
            alert(`Lỗi từ server (${response.status}): ` + responseText);
            console.error(`Lỗi HTTP ${response.status} khi ${type}:`, responseText);
        }
    } catch (error) {
        console.error(`Có lỗi xảy ra khi thực hiện ${type} request:`, error);
        alert('Đã xảy ra lỗi kết nối. Vui lòng thử lại.');
    }
}

//Dùng để test bằng role admin
function showLoginModal(type) {
    const username = prompt('Nhập tên đăng nhập hoặc email:');
    if (!username) {
        alert('Tên đăng nhập hoặc email không được để trống.');
        return;
    }
    const password = prompt('Nhập mật khẩu:');
    if (!password) {
        alert('Mật khẩu không được để trống.');
        return;
    }
    let email = null;

    if (type === 'register') {
        email = prompt('Nhập địa chỉ email (tùy chọn):');
    }

    demoLoginRegister(type, username, password, email);
}

// --- Comment API Integration ---
const COMMENTS_API_URL = `${API_BASE_URL}/commentsTrangDoc.php`;

const commentsList = document.getElementById('comments-list');
const commentTextInput = document.getElementById('comment-text');
const submitCommentIconButton = document.getElementById('submit-comment-icon');
const commentsCountSpan = document.querySelector('.comments-count');
const commentCountFooterSpan = document.getElementById('comment-count-footer');
const sortLabel = document.getElementById('sort-label');
const sortOptionsContainer = document.querySelector('.sort-options');

const currentComicId = getUrlParam('comic_id');
// currentChapterIdurl không còn được dùng trực tiếp cho saveHistory nữa, nhưng vẫn có thể dùng để lấy chapter_number ban đầu
const currentChapterIdurl = getUrlParam('chapter_number');


const SORT_OPTIONS = {
    'newest': 'Mới nhất',
    'oldest': 'Cũ nhất',
    'featured': 'Nổi bật'
};

let currentSortOrder = 'newest';

function updateCommentCountDisplay(count) {
    if (commentsCountSpan) {
        commentsCountSpan.textContent = `${count} Bình luận`;
    }
    if (commentCountFooterSpan) {
        commentCountFooterSpan.textContent = count;
    }
}

async function displayComments() {
    if (!commentsList || !currentComicId) {
        console.warn('Element #comments-list not found or comicId missing. Cannot display comments.');
        return;
    }
    commentsList.innerHTML = '<p style="text-align: center; color: #a0aec0;">Đang tải bình luận...</p>';
    try {
        const response = await fetch(`${COMMENTS_API_URL}?comicId=${currentComicId}&sort=${currentSortOrder}`);
        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || 'Không thể tải bình luận.');
        }
        const comments = await response.json();
        commentsList.innerHTML = '';
        if (comments.length === 0) {
            commentsList.innerHTML = '<p style="text-align: center; color: #a0aec0;">Chưa có bình luận nào. Hãy là người đầu tiên bình luận!</p>';
        } else {
            comments.forEach(comment => {
                const commentItem = document.createElement('div');
                commentItem.classList.add('comment-item');
                commentItem.dataset.id = comment.COMMENT_ID;
                const date = new Date(comment.CREATE_AT);
                const formattedDate = date.toLocaleString('vi-VN', { year: 'numeric', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' });

                // Kiểm tra currentUser để xác định quyền xóa
                const canDelete = currentUser && (currentUser.ROLE === 'admin' || currentUser.USES_ID == comment.USES_ID); // Sử dụng == để so sánh số/chuỗi
                let deleteButtonHTML = '';
                if (canDelete) {
                    deleteButtonHTML = `<button class="delete-comment" data-id="${comment.COMMENT_ID}">Xóa</button>`;
                }
                commentItem.innerHTML = `
                    <div class="comment-header">
                        <img src="${comment.AuthorAvatar}" alt="Avatar" class="comment-avatar">
                        <strong>${comment.Author}</strong>
                    </div>
                    <p class="comment-content">${comment.CONTENT}</p>
                    <div class="comment-footer">
                        <span class="comment-date">${formattedDate}</span>
                        ${deleteButtonHTML}
                    </div>
                `;
                commentsList.appendChild(commentItem);
            });
            document.querySelectorAll('.delete-comment').forEach(button => {
                button.addEventListener('click', async (e) => {
                    const commentIdToDelete = e.target.dataset.id;
                    if (confirm('Bạn có chắc chắn muốn xóa bình luận này?')) {
                        try {
                            const response = await fetch(COMMENTS_API_URL, {
                                method: 'DELETE',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({ comment_id: commentIdToDelete })
                            });
                            const data = await response.json();
                            if (response.ok) {
                                alert(data.message);
                                displayComments();
                            } else {
                                alert('Lỗi: ' + data.message);
                            }
                        } catch (error) {
                            console.error('Lỗi khi xóa bình luận:', error);
                            alert('Đã xảy ra lỗi khi kết nối với server để xóa bình luận.');
                        }
                    }
                });
            });
        }
        updateCommentCountDisplay(comments.length);
    } catch (error) {
        console.error('Lỗi khi tải bình luận:', error);
        if (commentsList) {
            commentsList.innerHTML = `<p style="text-align: center; color: red;">Không thể tải bình luận: ${error.message}</p>`;
        }
    }
}

if (submitCommentIconButton) {
    submitCommentIconButton.addEventListener('click', async () => {
        if (!currentUser || !currentUser.USES_ID) {
            alert('Bạn cần đăng nhập để bình luận.');
            return;
        }
        const text = commentTextInput.value.trim();
        if (text) {
            try {
                const response = await fetch(COMMENTS_API_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        content: text,
                        comic_id: currentComicId
                    })
                });
                const data = await response.json();
                if (response.ok) {
                    alert(data.message);
                    commentTextInput.value = '';
                    displayComments();
                } else {
                    alert('Lỗi: ' + data.message);
                }
            } catch (error) {
                console.error('Lỗi khi gửi bình luận:', error);
                alert('Đã xảy ra lỗi khi kết nối với server để gửi bình luận.');
            }
        } else {
            alert('Vui lòng nhập nội dung bình luận.');
        }
    });
}

// HÀM MỚI: LƯU LỊCH SỬ ĐỌC
async function saveReadingHistory() {
    // Đảm bảo có currentUser và comicId, currentChapterId trước khi lưu
    if (!currentUser || !currentUser.USES_ID) {
        console.warn('Không có thông tin người dùng. Không lưu lịch sử đọc.');
            console.log("saveReadingHistory không có currentUser")

        return;
    }
    if (!currentComicId || !currentChapterId) { // Đã sửa từ currentChapterIdurl thành currentChapterId
        console.warn('Thiếu comicId hoặc currentChapterId. Không lưu lịch sử đọc.');
        return;
    }
    try {
        const formData = new FormData();
        formData.append('user_id', currentUser.USES_ID);
        formData.append('comic_id', currentComicId);
        formData.append('chapter_id', currentChapterId); // Gửi currentChapterId đã được lấy từ API
        const response = await fetch(`${API_BASE_URL}/saveHistory.php`, {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (response.ok && data.status === 'success') {
            console.log('Lịch sử đọc đã được lưu thành công:', data.message);
        } else {
            console.error('Lỗi khi lưu lịch sử đọc:', data.message || 'Lỗi không xác định.');
        }
    } catch (error) {
        console.error('Lỗi kết nối khi lưu lịch sử đọc:', error);
    }
}


// Hàm tải dữ liệu chapter và pages
async function fetchChapterData() {
    try {
        const comicId = getUrlParam('comic_id');
        if (!comicId) {
            document.getElementById('comicChapterHeading').textContent = 'Không tìm thấy ID truyện.';
            console.error('comic_id not found in URL.');
            showErrorMessage();
            return;
        }

        // Fetch comic title first
        const comicTitleResponse = await fetch(`${API_BASE_URL}/getComicDetailsTrangDoc.php?comic_id=${comicId}`);
        if (!comicTitleResponse.ok) {
            const errorData = await comicTitleResponse.json();
            throw new Error(errorData.message || 'Không thể tải thông tin truyện.');
        }
        const comicTitleData = await comicTitleResponse.json();
        if (comicTitleData.success && comicTitleData.title) {
            currentComicTitle = comicTitleData.title;
        } else {
            console.warn('Failed to load comic title or no title found.');
            currentComicTitle = 'Không tìm thấy tên truyện';
        }

        // Fetch chapter info
        const chapterInfoResponse = await fetch(`${API_BASE_URL}/getComicChapterInfoTrangDoc.php?comic_id=${comicId}&chapter_number=${currentChapterNumber}`);
        if (!chapterInfoResponse.ok) {
            const errorData = await chapterInfoResponse.json();
            throw new Error(errorData.message || 'Không thể tải thông tin chapter.');
        }
        const chapterInfoData = await chapterInfoResponse.json();
        console.log('Chapter Info Data received by JS:', chapterInfoData);
        if (chapterInfoData.success && chapterInfoData.data) {
            const info = chapterInfoData.data;
            totalChapters = info.total_chapters;
            currentChapterTitle = info.chapter_title;
            currentChapterId = info.CHAPTER_ID; // THÊM DÒNG NÀY ĐỂ LẤY CHAPTER_ID

            updateChapterNumbersDisplay();

            const chapterPageTitleElement = document.getElementById('chapterPageTitle');
            if (chapterPageTitleElement) {
                chapterPageTitleElement.textContent = `${currentComicTitle} - ${currentChapterTitle} - Chapter ${currentChapterNumber}`;
            }

            const comicChapterHeading = document.getElementById('comicChapterHeading');
            if (comicChapterHeading) {
                comicChapterHeading.textContent = `${currentComicTitle} - Chapter ${currentChapterNumber}`;
            }

            updateNavigationButtons();

            // GỌI HÀM LƯU LỊCH SỬ ĐỌC SAU KHI CÓ ĐỦ DỮ LIỆU
            saveReadingHistory();

        } else {
            console.error('Error fetching chapter info:', chapterInfoData.message);
            showErrorMessage();
            return;
        }

        // Fetch chapter pages
        const pagesResponse = await fetch(`${API_BASE_URL}/getChapterPagesTrangDoc.php?comic_id=${comicId}&chapter_number=${currentChapterNumber}`);
        if (!pagesResponse.ok) {
            const errorData = await pagesResponse.json();
            console.log("không thể tải page");
            throw new Error(errorData.message || 'Không thể tải trang truyện.');
        }
        const pagesData = await pagesResponse.json();

        const pagesContainer = document.getElementById('chapterImages');
        if (pagesContainer && pagesData.success && pagesData.data) {
            pagesContainer.innerHTML = '';
            pagesData.data.forEach(page => {
                const img = document.createElement('img');
                img.src = page.URL;
                img.alt = `Page ${page.PAGE_NUMBER}`;
                img.classList.add('chapter-page');
                img.onerror = function() {
                    console.warn(`Failed to load image: ${this.src}`);
                    brokenImageCount++;
                    if (brokenImageCount >= MAX_BROKEN_IMAGES_BEFORE_ERROR) {
                        showErrorMessage();
                    }
                };
                pagesContainer.appendChild(img);
            });
        }
        hideErrorMessage();
    } catch (error) {
        console.error('Network or server error during data fetch:', error);
        showErrorMessage();
    }
}

// Hàm updateNavigationButtons() được định nghĩa ở đây để đảm bảo nó được gọi đúng lúc
function updateNavigationButtons() {
    const prevChapterButton = document.getElementById('prevChapterBtn');
    const nextChapterButton = document.getElementById('nextChapterBtn');

    if (prevChapterButton) {
        if (currentChapterNumber <= 1) {
            prevChapterButton.classList.add('disabled');
            prevChapterButton.style.pointerEvents = 'none';
            prevChapterButton.setAttribute('aria-disabled', 'true');
        } else {
            prevChapterButton.classList.remove('disabled');
            prevChapterButton.style.pointerEvents = 'auto';
            prevChapterButton.removeAttribute('aria-disabled');
        }
    }
    if (nextChapterButton) {
        if (currentChapterNumber >= totalChapters) {
            nextChapterButton.classList.add('disabled');
            nextChapterButton.style.pointerEvents = 'none';
            nextChapterButton.setAttribute('aria-disabled', 'true');
        } else {
            nextChapterButton.classList.remove('disabled');
            nextChapterButton.style.pointerEvents = 'auto';
            nextChapterButton.removeAttribute('aria-disabled');
        }
    }
}

// HÀM MỚI: Cập nhật hiển thị các liên kết dựa trên vai trò
function updateRoleBasedLinks() {
    const role = localStorage.getItem("role"); // Lấy vai trò mới nhất
    const duyetBaiLink = document.getElementById("duyet-bai-link");
    const capQuyenLink = document.getElementById("cap-quyen-link");
    const quanLyLink = document.getElementById("quan-ly-link");
    const upLoadLink = document.getElementById("upload-link");

    // Ẩn tất cả theo mặc định
    if (duyetBaiLink) duyetBaiLink.style.display = "none";
    if (capQuyenLink) capQuyenLink.style.display = "none";
    if (quanLyLink) quanLyLink.style.display = "none";
    if (upLoadLink) upLoadLink.style.display = "none";

    // Hiển thị dựa trên vai trò
    if (role === "admin") {
        if (duyetBaiLink) duyetBaiLink.style.display = "flex";
        if (quanLyLink) quanLyLink.style.display = "flex";
    } else if (role === "user") {
        // Có lẽ bạn muốn hiển thị link "cap-quyen-link" cho user để yêu cầu quyền tác giả?
        if (capQuyenLink) capQuyenLink.style.display = "flex";
    } else if (role === "author") {
        if (upLoadLink) upLoadLink.style.display = "flex";
    }
}


document.addEventListener('DOMContentLoaded', () => {
    currentChapterNumber = parseInt(getUrlParam('chapter_number')) || 1;

    const prevChapterBtnElement = document.getElementById('prevChapterBtn');
    const nextChapterBtnElement = document.getElementById('nextChapterBtn');
    const menuToggle = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('sidebar');
    const loginIcon = document.getElementById('login-icon');
    const userMenu = document.getElementById('user-menu');
    const logoutLink = document.getElementById('logout');
    const avatarTopRight = document.getElementById('avatarTopRight');

    if (prevChapterBtnElement) {
        prevChapterBtnElement.addEventListener('click', function(e) {
            if (currentChapterNumber > 1) {
                e.preventDefault();
                currentChapterNumber--;
                const newUrl = new URL(window.location.href);
                newUrl.searchParams.set('chapter_number', currentChapterNumber);
                window.history.pushState({ path: newUrl.href }, '', newUrl.href);
                fetchChapterData();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    }

    if (nextChapterBtnElement) {
        nextChapterBtnElement.addEventListener('click', function(e) {
            if (currentChapterNumber < totalChapters) {
                e.preventDefault();
                currentChapterNumber++;
                const newUrl = new URL(window.location.href);
                newUrl.searchParams.set('chapter_number', currentChapterNumber);
                window.history.pushState({ path: newUrl.href }, '', newUrl.href);
                fetchChapterData();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    }

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function () {
            sidebar.classList.toggle('active');
        });
    }

    const menuItems = document.querySelectorAll('.menu-item');
    menuItems.forEach(item => {
        item.addEventListener('click', function () {
            menuItems.forEach(i => i.classList.remove('active'));
            this.classList.add('active');
        });
    });

    if (loginIcon && userMenu) {
        loginIcon.addEventListener('click', function (e) {
            e.stopPropagation();
            userMenu.style.display = (userMenu.style.display === 'flex') ? 'none' : 'flex';
        });

        document.addEventListener('click', function (e) {
            if (!userMenu.contains(e.target) && e.target !== loginIcon && e.target !== avatarTopRight) {
                userMenu.style.display = 'none';
            }
        });
    }

    if (logoutLink) {
        logoutLink.addEventListener('click', function(e) {
            e.preventDefault();
            logoutUser();
        });
    }

    // Thứ tự gọi hàm quan trọng:
    // 1. Kiểm tra trạng thái đăng nhập để lấy currentUser
    checkLoginStatus();
    // 2. Gọi fetchChapterData để tải nội dung và LƯU LỊCH SỬ ĐỌC (nếu user đã đăng nhập)
    fetchChapterData();
    // 3. Hiển thị bình luận (có thể phụ thuộc vào currentUser)
    displayComments();
    // 4. Cập nhật các liên kết dựa trên vai trò sau khi checkLoginStatus đã chạy
    updateRoleBasedLinks();

});

document.addEventListener('DOMContentLoaded', function() {
    const mainHeader = document.getElementById('main_header');
    let lastScrollTop = 0;
    const scrollThreshold = 30;

    function handleScroll() {
        const scrollTop = window.scrollY || document.documentElement.scrollTop;

        if (scrollTop <= scrollThreshold || scrollTop < lastScrollTop) {
            mainHeader.classList.add('visible');
        } else if (scrollTop > lastScrollTop && scrollTop > scrollThreshold) {
            mainHeader.classList.remove('visible');
        }

        lastScrollTop = scrollTop;
    }
    window.addEventListener('scroll', handleScroll);
});

document.addEventListener('DOMContentLoaded', function() {
    const chapterControl = document.querySelector('.chapter-control');
    let lastScrollTop = 0;
    const scrollThreshold = 30;

    function handleScroll() {
        const scrollTop = window.scrollY || document.documentElement.scrollTop;

        if (scrollTop > lastScrollTop && scrollTop > scrollThreshold) {
            if (chapterControl) chapterControl.classList.remove('visible');
        } else {
            if (chapterControl) chapterControl.classList.add('visible');
        }

        lastScrollTop = scrollTop;
    }

    window.addEventListener('scroll', handleScroll);
});

function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

function toggleMenu() {
    const hiddenMenu = document.querySelector('.hidden_menu');
    if (hiddenMenu) hiddenMenu.classList.toggle('open');
}

window.addEventListener('load', () => {
    console.log('Tất cả tài nguyên đã được tải.');
    if (loadTimeout) {
        clearTimeout(loadTimeout);
    }
    hideErrorMessage();
});