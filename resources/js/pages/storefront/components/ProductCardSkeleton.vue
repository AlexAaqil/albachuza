<template>
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="aspect-square bg-gray-200 animate-pulse"></div>
        
        <div class="p-4 space-y-3">
            <div class="h-3 w-16 bg-gray-200 rounded animate-pulse"></div>
            <div class="h-5 w-3/4 bg-gray-200 rounded animate-pulse"></div>
            <div class="h-4 w-full bg-gray-200 rounded animate-pulse"></div>
            <div class="h-4 w-2/3 bg-gray-200 rounded animate-pulse"></div>
            
            <div class="flex items-center gap-1">
                <div class="flex gap-0.5">
                    <div v-for="n in 5" :key="n" class="h-3 w-3 bg-gray-200 rounded animate-pulse"></div>
                </div>
                <div class="h-3 w-8 bg-gray-200 rounded animate-pulse"></div>
            </div>
            
            <div class="flex justify-between items-center">
                <div class="h-6 w-20 bg-gray-200 rounded animate-pulse"></div>
                <div class="h-3 w-16 bg-gray-200 rounded animate-pulse"></div>
            </div>
            
            <div class="h-10 w-full bg-gray-200 rounded animate-pulse"></div>
        </div>
    </div>
</template>