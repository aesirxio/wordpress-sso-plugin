const zip = require("gulp-zip")
const gulp = require('gulp')
const composer = require("gulp-composer")

async function cleanTask() {
    const del = await import("del")
    return del.deleteAsync('./dist/plugin/**', {force:true});
}

function movePluginFolderTask() {
    return gulp.src([
        './wp-content/plugins/aesirx-login/**',
        '!./wp-content/plugins/aesirx-login/assets/src/**'
    ]).pipe(gulp.dest('./dist/plugin/aesirx-login'))
}

function compressTask() {
    return gulp.src('./dist/plugin/**')
        .pipe(zip('plg_aesirx_login.zip'))
        .pipe(gulp.dest('./dist'));
}

function composerTask() {
    return composer({
        "working-dir": "./dist/plugin/aesirx-login"
    })
}

async function cleanComposerTask() {
    const del = await import("del")
    return del.deleteAsync('./dist/plugin/aesirx-login/composer.*', {force:true});
}

exports.zip = gulp.series(
    cleanTask,
    movePluginFolderTask,
    composerTask,
    cleanComposerTask,
    compressTask,
    cleanTask
);
