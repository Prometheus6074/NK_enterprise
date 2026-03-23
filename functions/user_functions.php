<?php

function getProfile($connect2db, $userId)
{
    $sql = "SELECT firstname, lastname, email FROM accounts WHERE id = $userId";
    $query = mysqli_query($connect2db, $sql);

    return mysqli_fetch_assoc($query);
}

function updateProfile($data, $connect2db, $userId, &$resultClass, &$result)
{
    $firstname = $data['firstname'];
    $lastname  = $data['lastname'];
    $email     = $data['email'];

    $sql = "
        UPDATE accounts
        SET firstname = '$firstname',
            lastname = '$lastname',
            email = '$email'
        WHERE id = $userId
    ";

    if (!mysqli_query($connect2db, $sql)) {
        $resultClass = "error";
        $result = mysqli_error($connect2db);
        return;
    }

    $resultClass = "success";
    $result = "Updated Successfully";

    $_SESSION['user']['firstname'] = $firstname;
    $_SESSION['user']['lastname'] = $lastname;
    $_SESSION['user']['email'] = $email;
}

function createPost($connect2db, $userId, $data, &$resultClass, &$result) //& mean use original variable from outside
{
    $postContent = $data['postContent'];
    $sql = "
        INSERT INTO posts (information, userID)
        VALUES (
            '$postContent',
            '$userId'
        )
    ";

    if (!mysqli_query($connect2db, $sql)) {
        $resultClass = "error";
        $result = mysqli_error($connect2db);
        return;
    }

    $resultClass = "success";
    $result = "Post Created Successfully";
}


function getPosts($connect2db, $userId) 
{
    $sql = "SELECT posts.*, accounts.firstname, accounts.lastname 
            FROM posts 
            LEFT JOIN accounts on accounts.id = posts.userID 
            ORDER BY posts.id DESC";
    $query = mysqli_query($connect2db, $sql);

    $posts = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $posts[] = $row;
    }
    return $posts;
}

function createComment($connect2db, $postId, $userId, $commentText, &$resultClass, &$result)
{
    $sql = "
        INSERT INTO comments (post_id, user_id, comment_text)
        VALUES (
            '$postId',
            '$userId',
            '$commentText'
        )
    ";

    if (!mysqli_query($connect2db, $sql)) {
        $resultClass = "error";
        $result = mysqli_error($connect2db);
        return;
    }

    $resultClass = "success";
    $result = "Comment Added Successfully";
}

function getComments($connect2db, $postId)
{
    $sql = "SELECT comments.*, accounts.firstname, accounts.lastname 
            FROM comments 
            LEFT JOIN accounts on accounts.id = comments.user_id 
            WHERE comments.post_id = '$postId' 
            ORDER BY comments.created_at ASC";
    $query = mysqli_query($connect2db, $sql);

    $comments = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $comments[] = $row;
    }
    return $comments;
}

function deletePost($connect2db, $postId, $userId, &$resultClass, &$result)
{
    // Check user owns the post
    $sql = "SELECT userID FROM posts WHERE id = '$postId' AND userID = '$userId'";
    $query = mysqli_query($connect2db, $sql);
    
    if (mysqli_num_rows($query) === 0) {
        $resultClass = "error";
        $result = "You can only delete your own posts";
        return;
    }
    
    // Delete the post 
    $deleteSql = "DELETE FROM posts WHERE id = '$postId'";
    if (!mysqli_query($connect2db, $deleteSql)) {
        $resultClass = "error";
        $result = mysqli_error($connect2db);
        return;
    }
    
    $resultClass = "success";
    $result = "Post deleted successfully";
}

function updatePost($connect2db, $postId, $userId, $newContent, &$resultClass, &$result)
{
    // Check if the user owns the post
    $sql = "SELECT userID FROM posts WHERE id = '$postId' AND userID = '$userId'";
    $query = mysqli_query($connect2db, $sql);
    
    if (mysqli_num_rows($query) === 0) {
        $resultClass = "error";
        $result = "You can only edit your own posts";
        return;
    }
    
    // Update the post
    $updateSql = "UPDATE posts SET information = '$newContent' WHERE id = '$postId'";
    if (!mysqli_query($connect2db, $updateSql)) {
        $resultClass = "error";x
        $result = mysqli_error($connect2db);
        return;
    }
    
    $resultClass = "success";
    $result = "Post updated successfully";
}

function getUserPosts($connect2db, $userId)
{
    $sql = "SELECT posts.*, accounts.firstname, accounts.lastname 
            FROM posts 
            LEFT JOIN accounts on accounts.id = posts.userID 
            WHERE posts.userID = '$userId'
            ORDER BY posts.id DESC";
    $query = mysqli_query($connect2db, $sql);

    $posts = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $posts[] = $row;
    }
    return $posts;
}