
function getUrlParam(name) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(name);
}

const API_BASE_URL = 'http://localhost/Webtruyen_3/PHP';
let currentUser = null; 
let currentComicTitle = '';
let currentChapterNumber;
let totalChapters = 0;
let currentChapterTitle = '';
let chapterIdsSorted = []; 
const currentComicId = getUrlParam('comic_id');
let currentChapterId = getUrlParam('chapter_id'); 

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
    
    if (currentUser === null) {
        console.warn("currentUser rỗng. Đang cố gắng lấy profile từ server để xác định trạng thái đăng nhập.");
        await fetchUserProfileFromServer(); // Luôn thử fetch từ server
    }

    // Cuối cùng, cập nhật UI dựa trên trạng thái currentUser
    updateUIForLoginStatus();
}


function updateUIForLoginStatus() {
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

const storedUserId = localStorage.getItem('user_id');   // Key bạn dùng để lưu ID người dùng
const storedUserRole = localStorage.getItem('user_role'); // Key bạn dùng để lưu vai trò (ví dụ: 'admin', 'user')

if (storedUserId && storedUserRole) {
    currentUser = {
        USES_ID: storedUserId,
        ROLE: storedUserRole
    };
} else {
    // Console log để debug nếu thông tin người dùng không có trong localStorage
    console.log("Người dùng chưa đăng nhập hoặc thông tin không có trong localStorage.");
    // Bạn có thể thêm logic để chuyển hướng đến trang đăng nhập nếu cần
}


async function displayComments() {
    // Đảm bảo các phần tử DOM và ID cần thiết đã có
    if (!commentsList || !currentComicId) { // Đã bỏ currentChapterId vì API GET comment không dùng nữa
        console.warn('Element #comments-list not found or comicId missing. Cannot display comments.');
        // Hiển thị thông báo lỗi thân thiện cho người dùng
        if (commentsList) {
            commentsList.innerHTML = '<p style="text-align: center; color: #a0aec0;">Không thể tải bình luận (thiếu thông tin truyện).</p>';
        }
        return;
    }

    commentsList.innerHTML = '<p style="text-align: center; color: #a0aec0;">Đang tải bình luận...</p>';

    try {
        // API GET comment của bạn không cần chapterID nữa, theo file PHP bạn cung cấp
        const response = await fetch(`${COMMENTS_API_URL}?comicId=${currentComicId}&sort=${currentSortOrder}`);

        if (!response.ok) {
            const errorData = await response.json(); // Cố gắng đọc lỗi từ JSON
            throw new Error(errorData.message || 'Không thể tải bình luận từ server.');
        }

        const responseData = await response.json();

        // Vẫn đảm bảo rằng responseData.comments tồn tại và là mảng
        const comments = responseData.comments;

        commentsList.innerHTML = ''; // Xóa thông báo "Đang tải"

        // Kiểm tra xem 'comments' có phải là mảng và có rỗng không
        if (!Array.isArray(comments) || comments.length === 0) {
            // Nếu không phải mảng hoặc mảng rỗng, hiển thị thông báo không có bình luận
            commentsList.innerHTML = '<p style="text-align: center; color: #a0aec0;">Chưa có bình luận nào. Hãy là người đầu tiên bình luận!</p>';
        } else {
            // Nếu có bình luận, duyệt qua từng bình luận và hiển thị
            comments.forEach(comment => {
                const commentItem = document.createElement('div');
                commentItem.classList.add('comment-item');
                commentItem.dataset.id = comment.COMMENT_ID; // Lưu ID bình luận vào dataset

                // Định dạng ngày giờ
                const date = new Date(comment.CREATE_AT);
                const formattedDate = date.toLocaleString('vi-VN', { year: 'numeric', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' });

                // Logic hiển thị nút xóa:
                // `currentUser` phải tồn tại (đã đăng nhập) VÀ
                // (vai trò của `currentUser` là 'admin' HOẶC ID của `currentUser` trùng với ID tác giả bình luận)
                const canDelete = currentUser && (currentUser.ROLE === 'admin' || currentUser.USES_ID == comment.USES_ID);
                let deleteButtonHTML = '';
                if (canDelete) {
                    deleteButtonHTML = `<button class="delete-comment" data-id="${comment.COMMENT_ID}">Xóa</button>`;
                }

                // Đặt nội dung HTML cho bình luận
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
                commentsList.appendChild(commentItem); // Thêm bình luận vào danh sách
            });

            // Gán sự kiện click cho các nút xóa bình luận (sau khi chúng đã được tạo)
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
                                body: JSON.stringify({
                                    comment_id: commentIdToDelete,
                                    // Gửi user_id và role từ biến currentUser đã được khởi tạo từ localStorage
                                    uses_id: currentUser ? currentUser.USES_ID : null,
                                    role: currentUser ? currentUser.ROLE : null
                                })
                            });
                            const data = await response.json();
                            if (response.ok) { // Kiểm tra mã trạng thái HTTP 2xx
                                alert(data.message);
                                displayComments(); // Tải lại bình luận sau khi xóa thành công
                            } else { // Xử lý các lỗi từ server (mã trạng thái 4xx, 5xx)
                                alert('Lỗi: ' + data.message);
                                console.error('Phản hồi lỗi từ server:', data);
                            }
                        } catch (error) {
                            console.error('Lỗi khi xóa bình luận:', error);
                            alert('Đã xảy ra lỗi khi kết nối với server để xóa bình luận.');
                        }
                    }
                });
            });
        }
        // Cập nhật số lượng bình luận hiển thị
        updateCommentCountDisplay(Array.isArray(comments) ? comments.length : 0);
    } catch (error) {
        console.error('Lỗi khi tải bình luận:', error);
        if (commentsList) {
            commentsList.innerHTML = `<p style="text-align: center; color: red;">Không thể tải bình luận: ${error.message}</p>`;
        }
    }
}

// Xử lý gửi bình luận (đã điều chỉnh để phù hợp với currentUser và lược bỏ chapter_id khỏi API)
if (submitCommentIconButton) {
    submitCommentIconButton.addEventListener('click', async () => {
        // Kiểm tra xem người dùng đã đăng nhập chưa thông qua currentUser
        if (!currentUser || !currentUser.USES_ID) {
            alert('Bạn cần đăng nhập để bình luận.');
            return;
        }

        const commentText = commentTextInput.value.trim();
        if (!commentText) {
            alert('Vui lòng nhập nội dung bình luận.');
            return;
        }

        // Đảm bảo có comic_id
        if (!currentComicId) { // Đã loại bỏ chapterID khỏi API POST comment của bạn
            console.error('Lỗi: Thiếu comic_id. Không thể gửi bình luận.');
            alert('Lỗi khi gửi bình luận: Thiếu thông tin truyện.');
            return;
        }

        try {
            const payload = {
                content: commentText,
                comic_id: currentComicId,
                uses_id: currentUser.USES_ID // Đã sửa tên trường thành uses_id để khớp với PHP
            };

            const headers = {
                'Content-Type': 'application/json',
            };

            const response = await fetch(COMMENTS_API_URL, {
                method: 'POST',
                headers: headers,
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (response.ok && data.success) { // Giả định server trả về {success: true, message: "..."}
                alert(data.message);
                commentTextInput.value = ''; // Xóa nội dung input
                displayComments(); // Tải lại bình luận
            } else {
                alert('Lỗi: ' + (data.message || 'Lỗi không xác định từ server.'));
                console.error('Phản hồi lỗi từ server:', data);
            }
        } catch (error) {
            console.error('Lỗi khi gửi bình luận:', error);
            alert('Đã xảy ra lỗi khi kết nối với server để gửi bình luận.');
        }
    });
}

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
    clearTimeout(loadTimeout); 
    hasCriticalErrorOccurred = false; 
    hideErrorMessage(); 

    if (!currentComicId || !currentChapterId) { 
        console.log(currentComicId, currentChapterId);
        document.getElementById('comicChapterHeading').textContent = 'Không tìm thấy ID truyện hoặc ID chương.';
        console.error('comic_id or chapter_id not found in URL.');
        showErrorMessage();
        return;
    }

    try {
        // Fetch comic title (không thay đổi)
        const comicTitleResponse = await fetch(`${API_BASE_URL}/getComicDetailsTrangDoc.php?comic_id=${currentComicId}`);
        if (!comicTitleResponse.ok) {
            const errorData = await comicTitleResponse.json();
            throw new Error(errorData.message || 'Không thể tải thông tin truyện.');
        }
        const comicTitleData = await comicTitleResponse.json();
        if (comicTitleData.success && comicTitleData.title) {
            currentComicTitle = comicTitleData.title;
            document.getElementById('comicChapterHeading').textContent = comicTitleData.title;
            document.getElementById('chapterPageTitle').textContent = comicTitleData.title;
        } else {
            console.warn('Failed to load comic title or no title found.');
            currentComicTitle = 'Không tìm thấy tên truyện';
        }

        // Fetch chapter info và danh sách chapter_id_sorted
        // Endpoint này cần trả về thông tin chương hiện tại và MẢNG chapter_id đã sắp xếp của toàn bộ truyện
        const chapterInfoResponse = await fetch(`${API_BASE_URL}/getComicChapterInfoTrangDoc.php?comic_id=${currentComicId}&chapter_id=${currentChapterId}`); 
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
            currentChapterNumber = info.CHAPTER_NUMBER; // Lấy chapter number từ API

            // Cập nhật biến toàn cục chapterIdsSorted từ API
            if (Array.isArray(info.chapter_ids_sorted)) {
                chapterIdsSorted = info.chapter_ids_sorted;
                console.log("Danh sách chapter ID đã tải:", chapterIdsSorted);
            } else {
                console.warn("Dữ liệu chapter_ids_sorted không phải là mảng hoặc thiếu từ API.");
                chapterIdsSorted = []; 
            }

            updateChapterNumbersDisplay();
            updateNavigationButtons();
            saveReadingHistory(); 
            
        } else {
            console.error('Error fetching chapter info:', chapterInfoData.message);
            showErrorMessage();
            return; // Dừng lại nếu không lấy được thông tin chương
        }

        // Fetch pages for the current chapter
        const pagesResponse = await fetch(`${API_BASE_URL}/getChapterPagesTrangDoc.php?chapter_id=${currentChapterId}`); 
        if (!pagesResponse.ok) {
            const errorData = await pagesResponse.json();
            console.log("Không thể tải page");
            throw new Error(errorData.message || 'Không thể tải trang truyện.');
        }
        const pagesData = await pagesResponse.json();

        const pagesContainer = document.getElementById('chapterImages');
        if (pagesContainer && pagesData.success && pagesData.data) {
            pagesContainer.innerHTML = ''; // Xóa các hình ảnh cũ
            brokenImageCount = 0; // Reset số lượng hình ảnh bị hỏng
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
        hideErrorMessage(); // Ẩn thông báo lỗi nếu mọi thứ tải thành công
    } catch (error) {
        console.error('Network or server error during data fetch:', error);
        showErrorMessage(); // Hiển thị thông báo lỗi nếu có lỗi
    }
}

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

        if (capQuyenLink) capQuyenLink.style.display = "flex";
    } else if (role === "author") {
        if (upLoadLink) upLoadLink.style.display = "flex";
    }
}

function updateChapter(newChapterId) {
    currentChapterId = newChapterId; // Cập nhật chapter_id hiện tại
    const newUrl = new URL(window.location.href);
    newUrl.searchParams.set('comic_id', currentComicId); 
    newUrl.searchParams.set('chapter_id', currentChapterId); 
    newUrl.searchParams.delete('chapter_number'); // Đảm bảo xóa tham số cũ

    window.history.pushState({ path: newUrl.href }, '', newUrl.href);
    
    fetchChapterData(); 
    displayComments(); 
    window.scrollTo({ top: 0, behavior: 'smooth' }); 
}


document.addEventListener('DOMContentLoaded', async () => { 

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
            e.preventDefault();
            const currentIndex = chapterIdsSorted.indexOf(currentChapterId);
            if (currentIndex > 0) { // Nếu không phải là chương đầu tiên
                const prevChapterId = chapterIdsSorted[currentIndex - 1];
                updateChapter(prevChapterId); 
            } else {
                alert('Đây là chương đầu tiên.');
            }
        });
    }

    if (nextChapterBtnElement) {
        nextChapterBtnElement.addEventListener('click', function(e) {
            e.preventDefault();
            const currentIndex = chapterIdsSorted.indexOf(currentChapterId);
            if (currentIndex !== -1 && currentIndex < chapterIdsSorted.length - 1) { 
                const nextChapterId = chapterIdsSorted[currentIndex + 1];
                updateChapter(nextChapterId);
            } else {
                alert('Đây là chương cuối cùng.');
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
        await checkLoginStatus(); 

    await fetchChapterData();

    displayComments();

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