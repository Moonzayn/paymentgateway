package com.payment.app.ui.pos

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.payment.app.data.model.*
import com.payment.app.data.repository.PosRepository
import com.payment.app.util.Resource
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import javax.inject.Inject

@HiltViewModel
class PosViewModel @Inject constructor(
    private val posRepository: PosRepository
) : ViewModel() {

    private val _cart = MutableLiveData<MutableList<PosItem>>(mutableListOf())
    val cart: LiveData<MutableList<PosItem>> = _cart

    private val _total = MutableLiveData(0)
    val total: LiveData<Int> = _total

    private val _qrisState = MutableLiveData<Resource<QrisCreateResponse>>()
    val qrisState: LiveData<Resource<QrisCreateResponse>> = _qrisState

    private val _qrisStatus = MutableLiveData<Resource<QrisStatusResponse>>()
    val qrisStatus: LiveData<Resource<QrisStatusResponse>> = _qrisStatus

    private val _saveState = MutableLiveData<Resource<PosSimpanResponse>>()
    val saveState: LiveData<Resource<PosSimpanResponse>> = _saveState

    private val _transactionDetail = MutableLiveData<Resource<PosDetailResponse>>()
    val transactionDetail: LiveData<Resource<PosDetailResponse>> = _transactionDetail

    private var pollingJob: Job? = null

    fun addToCart(item: PosItem) {
        val currentCart = _cart.value ?: mutableListOf()
        val existingItem = currentCart.find { it.id == item.id && !item.isManual }

        if (existingItem != null) {
            existingItem.qty += item.qty
        } else {
            currentCart.add(item)
        }

        _cart.value = currentCart
        calculateTotal()
    }

    fun removeFromCart(index: Int) {
        val currentCart = _cart.value ?: mutableListOf()
        if (index in currentCart.indices) {
            currentCart.removeAt(index)
            _cart.value = currentCart
            calculateTotal()
        }
    }

    fun updateQuantity(index: Int, qty: Int) {
        val currentCart = _cart.value ?: mutableListOf()
        if (index in currentCart.indices) {
            if (qty <= 0) {
                currentCart.removeAt(index)
            } else {
                currentCart[index].qty = qty
            }
            _cart.value = currentCart
            calculateTotal()
        }
    }

    fun clearCart() {
        _cart.value = mutableListOf()
        _total.value = 0
    }

    private fun calculateTotal() {
        val currentCart = _cart.value ?: mutableListOf()
        _total.value = currentCart.sumOf { it.harga * it.qty }
    }

    fun createQrisPayment() {
        val totalAmount = _total.value ?: 0
        if (totalAmount <= 0) return

        viewModelScope.launch {
            _qrisState.value = Resource.Loading()
            val reference = posRepository.generateReference()
            _qrisState.value = posRepository.createQrisPayment(reference, totalAmount)
        }
    }

    fun startPolling(reference: String) {
        pollingJob?.cancel()
        pollingJob = viewModelScope.launch {
            while (true) {
                delay(1000)
                val result = posRepository.getQrisStatus(reference)
                _qrisStatus.value = result

                if (result is Resource.Success && result.data?.paid == true) {
                    break
                }
            }
        }
    }

    fun stopPolling() {
        pollingJob?.cancel()
        pollingJob = null
    }

    fun saveTransaction(metodeBayar: String, uangDiberikan: Int?, qrisReference: String?, qrisString: String?) {
        val currentCart = _cart.value ?: mutableListOf()
        if (currentCart.isEmpty()) return

        viewModelScope.launch {
            _saveState.value = Resource.Loading()
            _saveState.value = posRepository.saveTransaction(
                metodeBayar = metodeBayar,
                reference = qrisReference,
                qrisString = qrisString,
                uangDiberikan = uangDiberikan,
                items = currentCart
            )
        }
    }

    fun loadTransactionDetail(invoice: String) {
        viewModelScope.launch {
            _transactionDetail.value = Resource.Loading()
            _transactionDetail.value = posRepository.getTransactionDetail(invoice)
        }
    }

    override fun onCleared() {
        super.onCleared()
        stopPolling()
    }

    // Sample products for demo
    fun getSampleProducts(): List<PosItem> {
        return listOf(
            PosItem(1, "Kopi Susu", 15000, 0),
            PosItem(2, "Kopi Hitam", 12000, 0),
            PosItem(3, "Teh Manis", 10000, 0),
            PosItem(4, "Mie Goreng", 18000, 0),
            PosItem(5, "Nasi Goreng", 20000, 0),
            PosItem(6, "Ayam Goreng", 25000, 0),
            PosItem(7, "Es Teh", 8000, 0),
            PosItem(8, "Kopi Es", 15000, 0)
        )
    }
}
