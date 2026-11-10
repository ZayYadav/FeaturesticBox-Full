# Native C++ Bridge for FeaturesticBox Loader
cmake_minimum_required(VERSION 3.22.1)
project("featurestic_loader_native")

add_library(
    featurestic_loader_native
    SHARED
    native-lib.cpp
)

find_library(
    log-lib
    log
)

target_link_libraries(
    featurestic_loader_native
    ${log-lib}
)
